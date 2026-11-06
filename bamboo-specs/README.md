# Bamboo Specs

YAML specs for the Bamboo plans and the deployment project of the WordPress plugin. The plan `WS-WP` mirrors the live
plan as exported on 2026-11-06; the release plan `WP-WP` and the deployment project "WordPress Plugin" are new. The
setup follows `wink-travel/monorepo-typescript` (see its `bamboo-specs/README.md` for the long version).
Target: Bamboo Data Center 12.1.3.

**This repository is public. Never put a credential in these files.** `npm`-free: `node scripts/verify-bamboo-specs.mjs`
rejects the common token shapes, inline `${bamboo.VAR}`, the `anonymous` role, CRLF and any spec file other than
`bamboo.yml`.

## Layout

[`bamboo.yml`](./bamboo.yml) is the only spec file, on purpose: Bamboo reads `bamboo-specs/bamboo.yml` at the repository
root and offers no custom path. It holds 6 YAML documents; each plan or deployment is followed by its permissions
document.

| Document | What it does |
| --- | --- |
| Plan `WS-WP` (WordPress Plugin, staging) | `composer install` and PHPUnit on every merge to `master` (polls every 3 minutes). Deploys nothing. |
| Plan `WP-WP` (WordPress Plugin, release) | Builds a **release tag**: `composer install`, PHPUnit, then records the tag's version and commit in the `release` artifact (`release.txt`). |
| Deployment project "WordPress Plugin" | Source plan `WP-WP`, environment `WordPress.org`. On a successful tag build it checks out the same commit and runs `scripts/deployToWordPressOrg.bash`. |

## Releasing

1. `./release.bash` (on `master`) writes the version into `wink.php` and `README.txt`, updates `CHANGELOG.md`, commits,
   tags `X.Y.Z` and pushes the commit and the tag together.
2. Bamboo's tag trigger (`^\d+\.\d+\.\d+$`) starts `WP-WP`. It matches bare semver only, so the old `v1.x` tags never
   build or deploy. Allow about 5 minutes for the build to start.
3. On success the deployment publishes to `https://plugins.svn.wordpress.org/wink2travel`:
   1. **trunk** is synced from the checkout, minus everything in `.distignore`;
   2. **assets** is synced from `.wordpress-org/` (icons, banners, screenshots);
   3. the changes are committed (`Release X.Y.Z`);
   4. **tags/X.Y.Z** is created as a server-side copy of trunk, only after the commit succeeded.

The script stops before changing anything if `tags/X.Y.Z` already exists (a published tag is permanent), if `wink.php` or
`README.txt` does not carry the release version (WordPress serves `Stable tag` from trunk's `README.txt`), or if the
checkout is not the commit `WP-WP` tested. Re-running after a failure between the commit and the tag copy is safe: only
the tag is created.

`tag:` is not in the published YAML reference, but Bamboo 12.1.3 validates and applies it (verified on
monorepo-typescript); re-run the validator after every Bamboo upgrade.

## Secrets

| Where | What |
| --- | --- |
| Bamboo **global** variables `WORDPRESS_SVN_USERNAME` and `WORDPRESS_SVN_PASSWORD` | the WordPress.org SVN account. Bamboo exports them to scripts as `bamboo_WORDPRESS_SVN_USERNAME` / `bamboo_WORDPRESS_SVN_PASSWORD`; the script reads those. The password must be **masked** (a name containing PASSWORD is masked automatically). |

The script passes the password to `svn` on stdin (`--password-from-stdin`), so it is never on a command line, in a
process listing or in the log, and `--no-auth-cache` keeps svn from storing it. Never use `${bamboo.WORDPRESS_SVN_PASSWORD}`
in a script: it splices the value into the script text, which Bamboo logs.

## Agent prerequisites

The agent that runs the deployment needs `svn` 1.10 or newer (`brew install subversion`; Apple no longer ships it) and
`rsync`, on the PATH of a non-login shell (`/opt/homebrew/bin` is added by the deploy step). `composer` and `php` are
needed by both plans, as they were for the original plan.

## What was changed from the live export

- **`anonymous` removed** from the plan permissions; `logged-in` keeps view.
- **Linked repository** `WordPress Plugin Staging` (on `develop`) is now `wink-wordpress-plugin`, tracking `master`.
- **The "Single daily build" trigger is dropped.** The export could not express it ("not supported yet"), so a scan would
  have removed it anyway; builds now run on merges to `master` and on release tags.
- Scripts are LF block scalars, not `\r\n` strings.

## Enabling Repository Stored Specs

A scan is **not automatic** on this instance; until someone clicks scan, the plans stay as configured in the UI. Once
scanned, spec-managed plans are read-only: change the YAML.

1. `node scripts/verify-bamboo-specs.mjs` passes.
2. **Validate** in Bamboo first (**Specs** in the top navigation, pick the linked repository, paste `bamboo.yml`).
3. On the linked repository `wink-wordpress-plugin`: make sure it tracks **`master`**, enable Bamboo Specs with **"All
   projects"** (the file defines plans in two projects, `WS` and `WP`; only a Bamboo administrator can set this), and scan.
4. Right after the first scan check, on `WP-WP`: **no build started for any existing tag and nothing was deployed**
   (the old tags are `v`-prefixed and do not match the filter, but check). Confirm that the global variables exist.
5. Preview the sync before the first release: from a clone with `svn` installed, run
   `scripts/deployToWordPressOrg.bash --version <the version in wink.php> --dry-run`. It checks out the real SVN read-only,
   shows what would change and commits nothing (an existing tag is only a warning in a dry run).
