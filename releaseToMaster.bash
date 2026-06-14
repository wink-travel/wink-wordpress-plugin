#!/bin/bash

#
# Copyright (c) wink.travel 2022.
#

set -e  # Abort immediately if any command fails

echo "Releasing new version of Wink WordPress plugin using git flow..."

echo "Disabling git messages for a release"
export GIT_MERGE_AUTOEDIT=no
trap 'export GIT_MERGE_AUTOEDIT=yes' EXIT

# Commit any uncommitted work so the release starts from a clean state
git commit -a -m "chore: checking in anything in current branch [no ci]" 2>/dev/null || true

echo "Checking out develop branch..."
git checkout develop
git pull

CURRENT_VERSION=$(npx git-changelog-command-line --print-next-version --major-version-pattern BREAKING --minor-version-pattern feat)
PREV_VERSION=$(git describe --tags --abbrev=0)

echo ""
echo "Previous version : $PREV_VERSION"
echo "Next version     : $CURRENT_VERSION"
echo ""
echo "Unreleased changes:"
git cliff --unreleased --tag "$CURRENT_VERSION" --sort newest
echo ""

echo "Bumping version numbers in source files..."
sed -i '' "s/^Version:.*/Version: $CURRENT_VERSION/" README.txt
sed -i '' "s/^Stable tag:.*/Stable tag: $CURRENT_VERSION/" README.txt
sed -i '' "s/^\( \* Version:\s*\).*/\1$CURRENT_VERSION/" wink.php

git commit -a -m "chore: bump version to $CURRENT_VERSION [no ci]"
git push origin develop

echo "Starting release branch for $CURRENT_VERSION..."
git flow release start "$CURRENT_VERSION"

echo "Updating CHANGELOG.md on release branch..."
npx git-changelog-command-line -of CHANGELOG.md
git commit -a -m "docs: generated changelog and bumped version to $CURRENT_VERSION [no ci]"

echo "Checking for merge conflicts before finishing release..."
# In normal GitFlow, master is already an ancestor of the release branch, so the
# dry-run merge reports "Already up to date." WITHOUT creating a merge state — a
# following 'git merge --abort' would then fail ("MERGE_HEAD missing"). Short-
# circuit that case, and only abort when a merge is actually in progress.
if git merge-base --is-ancestor master HEAD; then
  echo "✓ master is already contained in this release branch — no merge needed"
elif git merge --no-commit --no-ff master >/dev/null 2>&1; then
  git merge --abort
  echo "✓ No conflicts detected between release and master"
else
  conflicted_files=$(git diff --name-only --diff-filter=U)
  echo ""
  echo "❌ RELEASE STOPPED: Merge conflicts detected between release branch and master"
  echo ""
  echo "Conflicted files:"
  echo "$conflicted_files" | sed 's/^/  - /'
  echo ""
  echo "Resolution steps:"
  echo "  1. Abort this release: git merge --abort"
  echo "  2. Abort release branch: git flow release cancel $CURRENT_VERSION"
  echo "  3. Switch to develop: git checkout develop"
  echo "  4. Resolve conflicts in the files listed above"
  echo "  5. Commit the resolution: git commit -m 'resolve: merge conflicts before release'"
  echo "  6. Push to origin: git push origin develop"
  echo "  7. Re-run this release script"
  echo ""
  [[ -f .git/MERGE_HEAD ]] && git merge --abort
  exit 1
fi

echo "Finishing release $CURRENT_VERSION..."
git flow release finish -m "$CURRENT_VERSION [no ci]" "$CURRENT_VERSION"

echo "Checking out master..."
git checkout master
git fetch --tags origin
git merge --ff-only origin/master
git push
git push --tags

echo "Checking out develop..."
git checkout develop
git fetch --tags origin
git merge --ff-only origin/develop
git push

echo "Generating release notes from commits since $PREV_VERSION..."
git log "$PREV_VERSION".."$CURRENT_VERSION" --pretty=format:"%s" \
  | grep -v '\[no ci\]' \
  | sed -E 's/^(feat|fix|chore|docs|style|refactor|perf|test)(\([^)]+\))?: /- \1\2: /' \
  | grep '^-' \
  | sort | uniq > release-notes.md

if [ ! -s release-notes.md ]; then
  echo "Warning: release notes are empty — no conventional commits found since $PREV_VERSION"
fi

echo "Creating GitHub release v$CURRENT_VERSION..."
gh release create "v$CURRENT_VERSION" -F release-notes.md --target master --latest
rm release-notes.md

echo "Merging CHANGELOG.md from master into develop..."
git merge master --no-edit -m ":twisted_rightwards_arrows: doc: merged CHANGELOG.md from master into develop branch" --strategy-option theirs
git push

echo "Wink WordPress plugin $CURRENT_VERSION has been successfully released"
