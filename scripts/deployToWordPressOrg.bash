#!/usr/bin/env bash
#
# Copyright (c) wink.travel 2026
#
# Publishes the plugin at THIS checkout to the WordPress.org plugin SVN
# (https://plugins.svn.wordpress.org/wink2travel):
#   1. trunk/   the plugin files, minus everything listed in .distignore
#   2. assets/  the contents of .wordpress-org/ (icons, banners, screenshots)
#   3. tags/<version>  a server-side copy of trunk, made only after the trunk commit succeeded
#
# Usage: scripts/deployToWordPressOrg.bash --version <MAJOR.MINOR.PATCH> [--dry-run]
#
#   --version   the release being published. It must equal `Version` in wink.php and both `Version`
#               and `Stable tag` in README.txt, or nothing is touched: WordPress serves the
#               `Stable tag` from trunk's README.txt, so a mismatch would publish the wrong release.
#   --dry-run   check out, sync and show what would be committed; commit nothing, tag nothing. Needs
#               no credentials (the SVN is publicly readable). An existing tag is only a warning, so
#               it can be run against an already published version to preview the sync.
#
# Credentials come from the environment, never from arguments or files: WORDPRESS_SVN_USERNAME and
# WORDPRESS_SVN_PASSWORD (the Bamboo global variables of the same name, which Bamboo exports as
# bamboo_WORDPRESS_SVN_USERNAME / bamboo_WORDPRESS_SVN_PASSWORD; both spellings are read). The password
# reaches svn on stdin (--password-from-stdin), so it is not in any process listing, and it is never
# echoed. svn's own credential cache is off (--no-auth-cache).
#
# SVN_URL overrides the repository URL (used by the tests).
#
# A tag in the SVN is permanent: if tags/<version> already exists the script stops before it changes
# anything. Re-running after a failure BETWEEN the trunk commit and the tag copy is safe: trunk is already
# up to date, so only the tag is created.
#
# Needs: svn >= 1.10 (brew install subversion) and rsync. bash 3.2 compatible.
set -euo pipefail
set +x

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SVN_URL="${SVN_URL:-https://plugins.svn.wordpress.org/wink2travel}"
USAGE='usage: deployToWordPressOrg.bash --version <MAJOR.MINOR.PATCH> [--dry-run]'

VERSION=""
DRY_RUN=0
while [[ $# -gt 0 ]]; do
  case "$1" in
    --version)
      [[ $# -ge 2 ]] || { echo "FAIL: --version requires a value"; exit 1; }
      VERSION="$2"; shift 2 ;;
    --version=*)
      VERSION="${1#--version=}"; shift ;;
    --dry-run)
      DRY_RUN=1; shift ;;
    *)
      echo "FAIL: unexpected argument '$1'"; echo "$USAGE"; exit 1 ;;
  esac
done

[[ -n "$VERSION" ]] || { echo "FAIL: --version is required"; echo "$USAGE"; exit 1; }
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || {
  echo "FAIL: --version must be MAJOR.MINOR.PATCH, got '${VERSION}'"; exit 1
}

SVN_USERNAME="${WORDPRESS_SVN_USERNAME:-${bamboo_WORDPRESS_SVN_USERNAME:-}}"
SVN_PASSWORD="${WORDPRESS_SVN_PASSWORD:-${bamboo_WORDPRESS_SVN_PASSWORD:-}}"
if [[ "$DRY_RUN" -eq 0 && ( -z "$SVN_USERNAME" || -z "$SVN_PASSWORD" ) ]]; then
  echo "FAIL: WORDPRESS_SVN_USERNAME and WORDPRESS_SVN_PASSWORD must be set (Bamboo global variables)"
  exit 1
fi

# --- The release version must be in the files WordPress reads --------------------------------------

header_value() { # <file> <sed pattern that captures the value in \1>
  sed -n "$2" "$1" | head -n 1 | tr -d '\r' | sed 's/[[:space:]]*$//'
}
PLUGIN_VERSION="$(header_value "$REPO_ROOT/wink.php" 's/^ \* Version:[[:space:]]*\(.*\)$/\1/p')"
README_VERSION="$(header_value "$REPO_ROOT/README.txt" 's/^Version:[[:space:]]*\(.*\)$/\1/p')"
README_STABLE="$(header_value "$REPO_ROOT/README.txt" 's/^Stable tag:[[:space:]]*\(.*\)$/\1/p')"
for pair in "wink.php Version:${PLUGIN_VERSION}" "README.txt Version:${README_VERSION}" "README.txt Stable tag:${README_STABLE}"; do
  found="${pair#*:}"
  if [[ "$found" != "$VERSION" ]]; then
    echo "FAIL: ${pair%%:*} says '${found}' but the release is '${VERSION}' — nothing published."
    echo "      ./release.bash writes the version into wink.php and README.txt before it tags."
    exit 1
  fi
done
[[ -f "$REPO_ROOT/.distignore" ]] || { echo "FAIL: ${REPO_ROOT}/.distignore not found — it decides what ships"; exit 1; }

for tool in svn rsync; do
  command -v "$tool" >/dev/null 2>&1 || { echo "FAIL: '${tool}' is not installed on this machine"; exit 1; }
done
svn help commit 2>/dev/null | grep -q -- '--password-from-stdin' || {
  echo "FAIL: this svn is too old (no --password-from-stdin, needs 1.10+) — nothing published."
  exit 1
}

SVN_FLAGS="--non-interactive --no-auth-cache"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

svn_auth() { # <svn args...>: run svn with the credentials on stdin
  printf '%s\n' "$SVN_PASSWORD" | svn "$@" --username "$SVN_USERNAME" --password-from-stdin $SVN_FLAGS
}

# A tag is permanent. Check before anything is changed.
if svn info "${SVN_URL}/tags/${VERSION}" $SVN_FLAGS >/dev/null 2>&1; then
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "WARN: ${SVN_URL}/tags/${VERSION} already exists; a real run would stop here. Continuing because this is a dry run."
  else
    echo "FAIL: ${SVN_URL}/tags/${VERSION} already exists — that version is already published. Nothing changed."
    exit 1
  fi
fi

echo "==> Checking out ${SVN_URL} (trunk and assets)..."
svn checkout --depth immediates "$SVN_URL" "$WORK/svn" $SVN_FLAGS >/dev/null
cd "$WORK/svn"
mkdir -p trunk assets
svn update --set-depth infinity trunk assets $SVN_FLAGS >/dev/null

echo "==> Syncing trunk from ${REPO_ROOT} (excluding what .distignore lists)..."
rsync -a --delete --exclude=.svn --exclude-from="$REPO_ROOT/.distignore" "$REPO_ROOT/" trunk/

if [[ -d "$REPO_ROOT/.wordpress-org" ]]; then
  echo "==> Syncing assets from .wordpress-org..."
  rsync -a --delete --exclude=.svn "$REPO_ROOT/.wordpress-org/" assets/
fi

svn add --force --parents --quiet trunk assets
# Files that vanished from the source are still versioned in the working copy ("!" in status).
svn status | sed -n 's/^![[:space:]]*//p' | while IFS= read -r gone; do
  svn rm --force --quiet "$gone"
done

# WordPress.org serves assets by MIME type; svn guesses wrongly for new binaries.
for f in assets/*.png assets/*.jpg assets/*.jpeg assets/*.svg assets/*.gif; do
  [[ -f "$f" ]] || continue
  case "$f" in
    *.png) mime=image/png ;;
    *.jpg | *.jpeg) mime=image/jpeg ;;
    *.svg) mime=image/svg+xml ;;
    *.gif) mime=image/gif ;;
  esac
  svn propset --quiet svn:mime-type "$mime" "$f"
done

CHANGES="$(svn status | wc -l | tr -d ' ')"
echo "==> ${CHANGES} change(s) to commit to trunk/assets:"
svn status | sed 's/^/    /'

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo
  echo "Dry run: nothing committed and tags/${VERSION} not created."
  exit 0
fi

if [[ "$CHANGES" -gt 0 ]]; then
  echo "==> Committing trunk and assets..."
  svn_auth commit -m "Release ${VERSION}"
else
  echo "==> trunk and assets already match this checkout; only the tag is missing."
fi

echo "==> Tagging ${VERSION} (server-side copy of trunk)..."
svn_auth copy "${SVN_URL}/trunk" "${SVN_URL}/tags/${VERSION}" -m "Tag ${VERSION}"

echo "Published ${VERSION} to ${SVN_URL}"
