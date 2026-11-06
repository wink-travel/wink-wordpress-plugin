/*
 * Copyright (c) wink.travel 2026
 */
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { checkLayout, checkSpecText } from './verify-bamboo-specs.mjs';

const OK = 'version: 2\nplan:\n  key: X\n';
const rules = (text) => checkSpecText(text).map((f) => f.rule);

test('a clean spec has no findings', () => {
  assert.deepEqual(checkSpecText(OK), []);
});

test('flags a GitHub token without echoing it', () => {
  const token = 'ghp_' + 'a1B2'.repeat(9);
  const findings = checkSpecText(
    `${OK}git remote set-url origin "https://u:${token}@github.com/o/r.git"\n`,
  );
  assert.ok(findings.some((f) => f.rule === 'looks like a GitHub token'));
  assert.ok(findings.every((f) => !f.rule.includes(token)));
});

test('flags credentials embedded in a URL', () => {
  assert.ok(
    rules(`${OK}curl https://user:hunter2@example.com/x\n`).includes(
      'looks like credentials in a URL',
    ),
  );
});

test('flags inline ${bamboo.VAR} but allows the safe ${bamboo_VAR} form', () => {
  assert.ok(
    rules(`${OK}export T="\${bamboo.SENTRY_AUTH_TOKEN}"\n`).some((r) =>
      r.startsWith('inline'),
    ),
  );
  assert.deepEqual(
    checkSpecText(`${OK}export T="\${bamboo_SENTRY_AUTH_TOKEN}"\n`),
    [],
  );
});

test('ignores an inline substitution that only appears in a comment', () => {
  assert.deepEqual(checkSpecText(`${OK}# do not use \${bamboo.X}\n`), []);
});

test('flags the anonymous role', () => {
  assert.ok(
    rules(`${OK}  roles:\n  - logged-in\n  - anonymous\n`).includes(
      'grants the anonymous role',
    ),
  );
});

test('flags carriage returns and a missing version', () => {
  assert.ok(
    rules('plan:\r\n  key: X\r\n').some((r) => r.startsWith('carriage return')),
  );
  assert.ok(rules('plan:\n  key: X\n').includes('missing "version: 2"'));
});

test('layout: a lone bamboo.yml is fine', () => {
  assert.deepEqual(checkLayout(['bamboo.yml']), []);
});

test('layout: other spec files or subdirectories are flagged because Bamboo ignores them', () => {
  assert.equal(checkLayout(['bamboo.yml', 'staging/bamboo.yml']).length, 1);
  assert.equal(checkLayout(['staging/bamboo.yml']).length, 2);
  assert.equal(checkLayout([]).length, 1);
});

test('layout: non-yaml files such as the README are not specs', () => {
  assert.deepEqual(checkLayout(['bamboo.yml', 'README.md']), []);
});

test('flags a job-level `enabled:` because Bamboo rejects the scan, but allows it under plan:', () => {
  const job = `${OK}Test:\n  key: TEST\n  enabled: false\n`;
  assert.ok(rules(job).some((r) => r.includes('`enabled` under `Test`')));
  const plan =
    'version: 2\nplan:\n  project-key: WP\n  key: AP\n  enabled: false\n';
  assert.deepEqual(checkSpecText(plan), []);
});
