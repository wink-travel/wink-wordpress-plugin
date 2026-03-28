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

echo "Finishing release $CURRENT_VERSION..."
git flow release finish -m "$CURRENT_VERSION [no ci]" "$CURRENT_VERSION"

echo "Checking out master..."
git checkout master
git pull --ff-only origin
git push
git push --tags

echo "Checking out develop..."
git checkout develop
git pull --ff-only origin
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
