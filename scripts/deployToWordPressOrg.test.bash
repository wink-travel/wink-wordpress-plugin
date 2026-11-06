#!/usr/bin/env bash
# deployToWordPressOrg.bash against a stubbed `svn` (no network, no WordPress.org). rsync is real, so
# what ships and what does not is checked on real files.
# Run with:  /bin/bash scripts/deployToWordPressOrg.test.bash
set -uo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
T="$(mktemp -d)"
trap 'rm -rf "$T"' EXIT
CALLS="$T/calls.log"; STDIN_LOG="$T/stdin.log"; TRUNK_LIST="$T/trunk.list"; ASSETS_LIST="$T/assets.list"
export CALLS STDIN_LOG TRUNK_LIST ASSETS_LIST
BIN="$T/bin"; REPO="$T/repo"
mkdir -p "$BIN" "$REPO/scripts" "$REPO/includes" "$REPO/.wordpress-org" "$REPO/.github/workflows" "$REPO/bamboo-specs" "$REPO/tests"

# --- a fixture plugin checkout ---------------------------------------------------------------------
cp "$SRC/scripts/deployToWordPressOrg.bash" "$REPO/scripts/"
cp "$SRC/.distignore" "$REPO/.distignore"
write_versions() { # <wink.php version> <readme Version> <readme Stable tag>
  printf '<?php\n/**\n * Plugin Name: Test\n * Version:%s\n * Author: Wink\n */\n' "$1" >"$REPO/wink.php"
  printf '=== wink2travel ===\nStable tag: %s\nVersion: %s\n\n== Description ==\nx\n' "$3" "$2" >"$REPO/README.txt"
}
write_versions 1.5.3 1.5.3 1.5.3
echo '<?php' >"$REPO/includes/a.php"
echo 'icon' >"$REPO/.wordpress-org/icon-128x128.png"
echo '<svg/>' >"$REPO/.wordpress-org/icon.svg"
echo 'x' >"$REPO/release.bash"; echo 'x' >"$REPO/cliff.toml"; echo 'x' >"$REPO/CHANGELOG.md"; echo 'x' >"$REPO/README.md"
echo 'x' >"$REPO/.github/workflows/ci.yml"; echo 'x' >"$REPO/bamboo-specs/bamboo.yml"
for f in composer.json composer.lock patchwork.json phpunit.xml; do echo x >"$REPO/$f"; done
echo 'x' >"$REPO/release.txt"; echo 'x' >"$REPO/scripts/other.bash"; echo 'x' >"$REPO/tests/T.php"
SCRIPT="$REPO/scripts/deployToWordPressOrg.bash"

# --- stubs -----------------------------------------------------------------------------------------
cat >"$BIN/svn" <<'STUB'
#!/usr/bin/env bash
echo "svn $*" >>"$CALLS"
case "$1" in
  --version) if [[ "${OLD_SVN:-0}" == 1 ]]; then echo 1.9.7; else echo "${SVN_VERSION:-1.15.0}"; fi; exit 0 ;;
  info) [[ "${TAG_EXISTS:-0}" == 1 ]] && exit 0; exit 1 ;;
  checkout)
    dest="${@: -1}"
    for a in "$@"; do case "$a" in --*) ;; *) last="$a" ;; esac; done
    dest="$last"; mkdir -p "$dest/trunk" "$dest/assets" "$dest/tags"
    [[ "${STALE:-0}" == 1 ]] && echo old >"$dest/trunk/stale.php"
    exit 0 ;;
  status)
    [[ "${STALE:-0}" == 1 && ! -f trunk/stale.php ]] && echo "!       trunk/stale.php"
    [[ "${CHANGES:-1}" == 1 ]] && echo "A       trunk/wink.php"
    exit 0 ;;
  commit)
    cat >"$STDIN_LOG"
    (cd trunk && find . -type f | sort >"$TRUNK_LIST"); (cd assets && find . -type f | sort >"$ASSETS_LIST")
    exit 0 ;;
  copy) cat >>"$STDIN_LOG"; exit 0 ;;
esac
exit 0
STUB
chmod +x "$BIN/svn"

pass=0; fail=0
ok()    { echo "  PASS  $1"; pass=$((pass+1)); }
bad()   { echo "  FAIL  $1"; fail=$((fail+1)); }
check() { if grep -qF -- "$2" "$3"; then ok "$1"; else bad "$1 (want: $2)"; fi; }
refute(){ if grep -qF -- "$2" "$3"; then bad "$1 (must not contain: $2)"; else ok "$1"; fi; }
rc_is() { if [[ "$(cat "$2")" == "$3" ]]; then ok "$1"; else bad "$1 (rc=$(cat "$2"), want $3)"; fi; }
URL=https://plugins.svn.wordpress.org/wink2travel

run() { local out="$1"; shift
  : >"$CALLS"; : >"$STDIN_LOG"; rm -f "$TRUNK_LIST" "$ASSETS_LIST"
  ( cd "$T" && PATH="$BIN:$PATH" SVN_URL="$URL" /bin/bash "$SCRIPT" "$@" ) >"$out" 2>&1
  echo $? >"${out}.rc"
}
export WORDPRESS_SVN_USERNAME=wink2travel-deployer WORDPRESS_SVN_PASSWORD=s3cr3t-PW-value

echo "== 0. the script is executable (Bamboo runs it directly) =="
[[ -x "$SRC/scripts/deployToWordPressOrg.bash" ]] && ok "deployToWordPressOrg.bash is executable" || bad "deployToWordPressOrg.bash is executable"

echo "== 1. happy path =="
run "$T/a" --version 1.5.3
rc_is  "exit 0" "$T/a.rc" 0
check  "commits trunk and assets once" "svn commit -m Release 1.5.3" "$CALLS"
n="$(grep -c '^svn commit ' "$CALLS")"; [[ "$n" == 1 ]] && ok "exactly one commit" || bad "exactly one commit ($n)"
check  "tags by server-side copy" "svn copy ${URL}/trunk ${URL}/tags/1.5.3 -m Tag 1.5.3" "$CALLS"
check  "non-interactive, no auth cache" "--non-interactive --no-auth-cache" "$CALLS"
check  "username passed" "--username wink2travel-deployer" "$CALLS"
check  "password read from stdin" "--password-from-stdin" "$CALLS"
refute "password never on a command line" "s3cr3t-PW-value" "$CALLS"
refute "password never printed" "s3cr3t-PW-value" "$T/a"
check  "password reached svn on stdin" "s3cr3t-PW-value" "$STDIN_LOG"
check  "plugin files ship" "./wink.php" "$TRUNK_LIST"
check  "readme ships" "./README.txt" "$TRUNK_LIST"
check  "includes ship" "./includes/a.php" "$TRUNK_LIST"
for f in composer.json composer.lock patchwork.json phpunit.xml tests; do
  refute "does not ship dev file: $f" "./$f" "$TRUNK_LIST"
done
for f in release.txt release.bash cliff.toml CHANGELOG.md README.md .github bamboo-specs scripts .wordpress-org; do
  refute "does not ship: $f" "./$f" "$TRUNK_LIST"
done
check  "icons go to assets" "./icon-128x128.png" "$ASSETS_LIST"
check  "svg icon goes to assets" "./icon.svg" "$ASSETS_LIST"
check  "png mime type set" "svn:mime-type image/png assets/icon-128x128.png" "$CALLS"
check  "svg mime type set" "svn:mime-type image/svg+xml assets/icon.svg" "$CALLS"
n_commit="$(grep -n '^svn commit ' "$CALLS" | cut -d: -f1)"; n_copy="$(grep -n '^svn copy ' "$CALLS" | cut -d: -f1)"
[[ "$n_commit" -lt "$n_copy" ]] && ok "tag only after the trunk commit" || bad "tag only after the trunk commit"

echo "== 2. files removed from the source are removed from trunk =="
STALE=1 run "$T/b" --version 1.5.3
rc_is  "exit 0" "$T/b.rc" 0
check  "svn rm of the vanished file" "svn rm --force --quiet trunk/stale.php" "$CALLS"
refute "stale file not in the committed trunk" "stale.php" "$TRUNK_LIST"

echo "== 3. version mismatches publish nothing =="
write_versions 1.5.2 1.5.3 1.5.3
run "$T/c" --version 1.5.3
rc_is  "wink.php disagrees: exit 1" "$T/c.rc" 1
check  "names the file" "wink.php Version says '1.5.2'" "$T/c"
[[ -s "$CALLS" ]] && bad "svn untouched (wink.php)" || ok "svn untouched (wink.php)"
write_versions 1.5.3 1.5.3 1.5.2
run "$T/c2" --version 1.5.3
rc_is  "Stable tag disagrees: exit 1" "$T/c2.rc" 1
check  "names Stable tag" "README.txt Stable tag says '1.5.2'" "$T/c2"
[[ -s "$CALLS" ]] && bad "svn untouched (Stable tag)" || ok "svn untouched (Stable tag)"
write_versions 1.5.3 1.5.3 1.5.3

echo "== 3b. a second Version line in the readme does not break the check =="
printf '=== wink2travel ===\nStable tag: 1.5.3\nVersion: 1.5.3\n\n== Changelog ==\nVersion: 1.4.0 (history)\nStable tag: 1.4.0\n' >"$REPO/README.txt"
run "$T/c3" --version 1.5.3 --dry-run
rc_is  "first match wins, exit 0" "$T/c3.rc" 0
write_versions 1.5.3 1.5.3 1.5.3

echo "== 4. a published tag is permanent =="
TAG_EXISTS=1 run "$T/d" --version 1.5.3
rc_is  "exit 1" "$T/d.rc" 1
check  "says so" "already exists" "$T/d"
refute "no commit" "svn commit" "$CALLS"
refute "no checkout" "svn checkout" "$CALLS"

echo "== 5. credentials and arguments =="
( unset WORDPRESS_SVN_PASSWORD; run "$T/e" --version 1.5.3 ); run_rc="$(cat "$T/e.rc")"
[[ "$run_rc" == 1 ]] && ok "missing password fails" || bad "missing password fails (rc=$run_rc)"
check  "names the variables" "WORDPRESS_SVN_USERNAME and WORDPRESS_SVN_PASSWORD" "$T/e"
[[ -s "$CALLS" ]] && bad "svn untouched without credentials" || ok "svn untouched without credentials"
( unset WORDPRESS_SVN_USERNAME WORDPRESS_SVN_PASSWORD; export bamboo_WORDPRESS_SVN_USERNAME=bamboo-user bamboo_WORDPRESS_SVN_PASSWORD=bamboo-pw; run "$T/e2" --version 1.5.3 )
rc_is  "Bamboo's bamboo_ spelling works" "$T/e2.rc" 0
check  "uses the Bamboo username" "--username bamboo-user" "$CALLS"
run "$T/f"
check  "--version required" "--version is required" "$T/f"
run "$T/f2" --version v1.5.3
check  "bare semver only" "must be MAJOR.MINOR.PATCH" "$T/f2"
run "$T/f3" --version 1.5.3 --bogus
check  "unknown option" "unexpected argument '--bogus'" "$T/f3"
OLD_SVN=1 run "$T/g" --version 1.5.3
rc_is  "old svn fails" "$T/g.rc" 1
check  "says why" "too old" "$T/g"
refute "an old svn changes nothing" "svn checkout" "$CALLS"
for v in 1.10.0 1.14.5 1.15.0 2.0.0; do
  SVN_VERSION=$v run "$T/g_$v" --version 1.5.3 --dry-run
  rc_is  "svn $v is accepted" "$T/g_$v.rc" 0
done
SVN_VERSION=garbage run "$T/g2" --version 1.5.3 --dry-run
rc_is  "an unreadable svn version fails" "$T/g2.rc" 1

echo "== 6. dry run =="
( unset WORDPRESS_SVN_USERNAME WORDPRESS_SVN_PASSWORD; run "$T/h" --version 1.5.3 --dry-run )
rc_is  "needs no credentials, exit 0" "$T/h.rc" 0
check  "reads the SVN" "svn checkout" "$CALLS"
refute "commits nothing" "svn commit" "$CALLS"
refute "tags nothing" "svn copy" "$CALLS"
check  "says dry run" "Dry run" "$T/h"

echo "== 6b. dry run against an already published version only warns =="
TAG_EXISTS=1 run "$T/h2" --version 1.5.3 --dry-run
rc_is  "exit 0" "$T/h2.rc" 0
check  "warns" "WARN:" "$T/h2"
refute "still commits nothing" "svn commit" "$CALLS"

echo "== 7. trunk already current: only the tag is made =="
CHANGES=0 run "$T/i" --version 1.5.3
rc_is  "exit 0" "$T/i.rc" 0
refute "no trunk commit" "svn commit" "$CALLS"
check  "tag still created" "svn copy ${URL}/trunk ${URL}/tags/1.5.3" "$CALLS"

echo
echo "passed=$pass failed=$fail"
[[ "$fail" -eq 0 ]]
