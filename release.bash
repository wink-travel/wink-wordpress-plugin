#!/bin/bash

#
# Copyright (c) wink.travel 2026
#
# Trunk-based release. `master` is the only long-lived branch; a release is a tag on it.
#
#   ./release.bash             # interactive: shows the next version, asks, then releases
#   ./release.bash --dry-run   # compute and print everything, change nothing
#   ./release.bash --yes       # no confirmation prompt
#
# What it does, in order: checks the working tree and that local master equals origin/master, works
# out the next version from the conventional commits since the latest tag (git-cliff, config in
# cliff.toml), writes it into wink.php (Version) and README.txt (Version and Stable tag), adds the
# CHANGELOG.md entry, commits all three in ONE commit (message ends in "[no ci]"), tags that commit with
# the bare version (for example 1.5.3), and pushes the commit and the tag together. Pushing the tag is
# what makes Bamboo test the tagged commit and publish it to the WordPress.org plugin SVN. It then
# creates the GitHub release.
#
# It never merges, never creates a release branch and never touches any other branch. If it fails
# before the push, nothing has left your machine: see the recovery message it prints.
#
# Needs: git, gh and git-cliff (brew install git-cliff).
#
# RELEASE_BRANCH overrides the branch it releases from (default: master), so the script can be
# dry-run from another branch.

set -euo pipefail

RELEASE_BRANCH="${RELEASE_BRANCH:-master}"
DRY_RUN=false
ASSUME_YES=false

for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=true ;;
    --yes | -y) ASSUME_YES=true ;;
    -h | --help)
      sed -n '4,23p' "$0" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *)
      echo "ERROR: unknown argument '$arg' (try --help)." >&2
      exit 2
      ;;
  esac
done

die() {
  echo "ERROR: $*" >&2
  exit 1
}

# For failures after files were changed: returning (not exiting) lets the ERR trap print the recovery
# message below.
fail() {
  echo "ERROR: $*" >&2
  return 1
}

# --- Guards ---

command -v gh > /dev/null || die "the GitHub CLI (gh) is required to create the release."
command -v git-cliff > /dev/null || die "git-cliff is required (brew install git-cliff)."

current_branch=$(git rev-parse --abbrev-ref HEAD)
[[ "$current_branch" == "$RELEASE_BRANCH" ]] ||
  die "you must be on '$RELEASE_BRANCH' to release. Currently on '$current_branch'."

[[ -z "$(git status --porcelain)" ]] ||
  die "working tree is dirty. Commit or stash your changes before releasing."

echo "==> Checking '$RELEASE_BRANCH' matches origin/$RELEASE_BRANCH..."
git fetch --quiet --tags origin
local_sha=$(git rev-parse HEAD)
remote_sha=$(git rev-parse "origin/$RELEASE_BRANCH")
if [[ "$local_sha" != "$remote_sha" ]]; then
  ahead=$(git rev-list --count "origin/$RELEASE_BRANCH..HEAD")
  behind=$(git rev-list --count "HEAD..origin/$RELEASE_BRANCH")
  die "local '$RELEASE_BRANCH' is $ahead commit(s) ahead and $behind behind origin. Releasing a commit that is not on origin would tag something nobody else has. Pull or push first."
fi

# --- Version calculation ---

# Releases are bare-semver or v-prefixed semver tags (cliff.toml: tag_pattern).
PREV_VERSION=$(git tag --list --sort=-v:refname | grep -E '^v?[0-9]+\.[0-9]+\.[0-9]+$' | head -n 1 || true)
NEXT_VERSION=$(git cliff --bumped-version 2> /dev/null | tail -n 1 | sed 's/^v//')
[[ "$NEXT_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] ||
  die "could not determine the next version (git-cliff printed '${NEXT_VERSION}')."

if git rev-parse --verify --quiet "refs/tags/$NEXT_VERSION" > /dev/null; then
  die "tag '$NEXT_VERSION' already exists. Nothing new to release since '${PREV_VERSION:-the start}'?"
fi

echo ""
echo "Previous version: ${PREV_VERSION:-none}"
echo "Next version:     ${NEXT_VERSION}"
echo "Commits since the previous version:"
if [[ -n "$PREV_VERSION" ]]; then
  git --no-pager log --oneline "${PREV_VERSION}..HEAD" | sed 's/^/  /'
else
  git --no-pager log --oneline | sed 's/^/  /'
fi
echo ""

if [[ "$DRY_RUN" == true ]]; then
  echo "==> Dry run: nothing was changed. A real run would now:"
  echo "      set the version to ${NEXT_VERSION} in wink.php and README.txt, add the CHANGELOG.md entry,"
  echo "      commit, tag ${NEXT_VERSION}, push '${RELEASE_BRANCH}' + tag ${NEXT_VERSION} together,"
  echo "      create the GitHub release. Bamboo then publishes ${NEXT_VERSION} to WordPress.org."
  exit 0
fi

if [[ "$ASSUME_YES" != true ]]; then
  read -rp "Release ${NEXT_VERSION} from '${RELEASE_BRANCH}'? Pushing the tag publishes it to WordPress.org. [y/N] " confirm
  [[ "$confirm" == "y" || "$confirm" == "Y" ]] || {
    echo "Release aborted."
    exit 0
  }
fi

# --- Recovery message if anything fails before the push ---

pushed=false
on_failure() {
  local code=$?
  if [[ "$pushed" == false ]]; then
    echo "" >&2
    echo "RELEASE FAILED before anything was pushed. Nothing left your machine." >&2
    echo "Undo the local changes with:" >&2
    echo "  git tag -d ${NEXT_VERSION} 2> /dev/null; git reset --hard origin/${RELEASE_BRANCH}" >&2
  else
    echo "" >&2
    echo "RELEASE FAILED after the push. The tag and commit are on origin." >&2
    echo "Create the GitHub release by hand: gh release create ${NEXT_VERSION} --target ${RELEASE_BRANCH} --latest" >&2
  fi
  exit "$code"
}
trap on_failure ERR

# --- Release tasks ---

echo "==> Setting the version to ${NEXT_VERSION}..."
# The plugin header in wink.php and the readme.txt header are what WordPress reads; they must both
# carry the release version, and deployToWordPressOrg.bash refuses a tag that disagrees with them.
sed -i.bak -e "s/^Version:.*/Version: ${NEXT_VERSION}/" -e "s/^Stable tag:.*/Stable tag: ${NEXT_VERSION}/" README.txt
sed -i.bak -e "s/^\( \* Version:[[:space:]]*\).*/\1${NEXT_VERSION}/" wink.php
rm -f README.txt.bak wink.php.bak
grep -q "^Version: ${NEXT_VERSION}\$" README.txt || fail "could not set Version in README.txt."
grep -q "^Stable tag: ${NEXT_VERSION}\$" README.txt || fail "could not set Stable tag in README.txt."
grep -q "^ \* Version:[[:space:]]*${NEXT_VERSION}\$" wink.php || fail "could not set Version in wink.php."

echo "==> Writing the changelog entry..."
# The new section goes below the title block and above the previous release; nothing else in the file
# changes.
section=$(mktemp)
git cliff --tag "$NEXT_VERSION" --unreleased --strip header --output "$section"
[[ -s "$section" ]] || fail "git-cliff produced no changelog entry for ${NEXT_VERSION}."
merged=$(mktemp)
awk -v sec="$section" '
  !done && /^## / { while ((getline line < sec) > 0) print line; print ""; done = 1 }
  { print }
  END { if (!done) { print ""; while ((getline line < sec) > 0) print line } }
' CHANGELOG.md > "$merged"
cat "$merged" > CHANGELOG.md
rm -f "$merged"

echo "==> Committing release artifacts..."
git add wink.php README.txt CHANGELOG.md
git commit -m "chore: release ${NEXT_VERSION} [no ci]"

echo "==> Tagging ${NEXT_VERSION}..."
git tag -a "$NEXT_VERSION" -m "$NEXT_VERSION"

# --- Push the release commit and the tag together ---

# --atomic: either both refs update or neither does, so a rejected branch push cannot leave a tag
# on origin that points at a commit the branch does not contain.
echo "==> Pushing '${RELEASE_BRANCH}' and tag ${NEXT_VERSION} to origin..."
git push --atomic origin "$RELEASE_BRANCH" "refs/tags/${NEXT_VERSION}"
pushed=true

# --- GitHub release ---

echo "==> Creating GitHub release..."
if [[ -s "$section" ]]; then
  gh release create "$NEXT_VERSION" -F "$section" --target "$RELEASE_BRANCH" --latest
else
  gh release create "$NEXT_VERSION" --notes "See CHANGELOG.md for release notes" --target "$RELEASE_BRANCH" --latest
fi
rm -f "$section"

echo "==> Release ${NEXT_VERSION} SUCCESSFUL. Bamboo will test it and publish it to WordPress.org."
