/*
 * Copyright (c) wink.travel 2026
 *
 * Guards the checked-in Bamboo specs (bamboo-specs/**\/*.yaml). Bamboo logs a script's text when it
 * substitutes a variable and these files are public to everyone with repo access, so a committed
 * credential or an inline ${bamboo.VAR} is a leak waiting to happen (see bamboo-specs/README.md).
 *
 *   node scripts/verify-bamboo-specs.mjs     # exits 1 with one line per finding
 */
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const SPECS_DIR = fileURLToPath(new URL('../bamboo-specs', import.meta.url));

/** Credential shapes. Matching text is never echoed back: only the rule name and line number. */
const SECRET_PATTERNS = [
  ['a GitHub token', /\bgh[pousr]_[A-Za-z0-9]{20,}/],
  ['a GitHub fine-grained token', /\bgithub_pat_[A-Za-z0-9_]{20,}/],
  ['a Sentry token', /\bsntry[su]_[A-Za-z0-9+/=_-]{20,}/],
  ['a GitLab token', /\bglpat-[A-Za-z0-9_-]{16,}/],
  ['an AWS access key id', /\bAKIA[0-9A-Z]{16}\b/],
  ['a private key', /-----BEGIN [A-Z ]*PRIVATE KEY-----/],
  ['credentials in a URL', /https?:\/\/[^\s/:@"']+:[^\s/@"']+@/],
];

/** `${bamboo.NAME}` splices the value into the script text before it runs; `${bamboo_NAME}` does not. */
const INLINE_SUBSTITUTION = /\$\{bamboo\.[A-Za-z0-9_.]+\}/;
const ANONYMOUS_ROLE = /^\s*-\s*anonymous\s*$/;

/**
 * Bamboo scans only `bamboo-specs/bamboo.yml` (or `.yaml`) and offers no custom path, so any other
 * spec file is silently ignored. @param {string[]} files paths relative to bamboo-specs/
 * @returns {string[]} problems with the set of files
 */
export function checkLayout(files) {
  const specs = files.filter((f) => /\.ya?ml$/.test(f));
  const problems = [];
  if (!specs.some((f) => /^bamboo\.ya?ml$/.test(f))) {
    problems.push(
      'missing bamboo-specs/bamboo.yml: Bamboo detects no other file name',
    );
  }
  for (const f of specs.filter((f) => !/^bamboo\.ya?ml$/.test(f))) {
    problems.push(
      `${f}: Bamboo ignores spec files other than bamboo-specs/bamboo.yml`,
    );
  }
  return problems;
}

/** @returns {Array<{line: number, rule: string}>} findings for one spec file's text */
export function checkSpecText(text) {
  const findings = [];
  const lines = text.split('\n');

  if (text.includes('\r')) {
    findings.push({
      line: 1,
      rule: 'carriage return: export scripts as block scalars with LF endings',
    });
  }
  if (!/^version: 2\s*$/m.test(text)) {
    findings.push({ line: 1, rule: 'missing "version: 2"' });
  }

  // `enabled` is valid under `plan:` only. Bamboo's YAML has no job-level flag and rejects the scan
  // with "Found following unused properties: <Job> / enabled" (found the hard way).
  let topKey = '';

  lines.forEach((line, index) => {
    const n = index + 1;
    const top = /^([^\s#-][^:]*):\s*$/.exec(line);
    if (top) {
      topKey = top[1];
    }
    if (/^ {2}enabled:/.test(line) && topKey !== 'plan') {
      findings.push({
        line: n,
        rule: `\`enabled\` under \`${topKey || 'the top level'}\`: Bamboo YAML only accepts it under \`plan:\``,
      });
    }
    for (const [name, pattern] of SECRET_PATTERNS) {
      if (pattern.test(line)) {
        findings.push({ line: n, rule: `looks like ${name}` });
      }
    }
    if (INLINE_SUBSTITUTION.test(line) && !line.trimStart().startsWith('#')) {
      findings.push({
        line: n,
        rule: 'inline ${bamboo.VAR}: read ${bamboo_VAR} instead',
      });
    }
    if (ANONYMOUS_ROLE.test(line)) {
      findings.push({ line: n, rule: 'grants the anonymous role' });
    }
  });

  return findings;
}

function specFiles(dir) {
  return readdirSync(dir).flatMap((entry) => {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) return specFiles(full);
    return /\.ya?ml$/.test(entry) ? [full] : [];
  });
}

function main() {
  const files = specFiles(SPECS_DIR);
  if (files.length === 0) {
    console.error('No Bamboo spec files found under bamboo-specs/.');
    process.exit(1);
  }
  let failed = 0;
  for (const problem of checkLayout(files.map((f) => relative(SPECS_DIR, f)))) {
    failed++;
    console.error(`bamboo-specs/${problem}`);
  }
  for (const file of files) {
    for (const { line, rule } of checkSpecText(readFileSync(file, 'utf8'))) {
      failed++;
      console.error(`${relative(process.cwd(), file)}:${line}: ${rule}`);
    }
  }
  if (failed > 0) {
    console.error(
      `\n${failed} problem(s) in bamboo-specs/. See bamboo-specs/README.md.`,
    );
    process.exit(1);
  }
  console.log(`bamboo-specs: ${files.length} files, no problems.`);
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  main();
}
