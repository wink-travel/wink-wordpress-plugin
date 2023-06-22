#!/bin/bash

#
# Copyright (c) iko.travel 2022.
#

echo "Disabling git messages for a release"
export GIT_MERGE_AUTOEDIT=no

echo "Releasing new version of Wink Affiliate WordPress plugin with git flow..."
echo "Enter version number. E.g. 1.2.3";

read versionNumber

versionNumber="v${versionNumber}";

git cliff --unreleased --tag $versionNumber --sort newest --prepend CHANGELOG.md

echo "Committing version changes for $versionNumber"
git commit -a -m "build: bookmark: merge to master

Version bump to $versionNumber registered

Ops: $USER
"

git push --follow-tags origin develop

echo "Calling 'git flow release $versionNumber'"
git flow release start $versionNumber

echo "Calling 'git flow finish -m $versionNumber $versionNumber'"
git flow release finish -m $versionNumber $versionNumber

echo "Checking out master..."
git checkout master

echo "Updating CHANGELOG.md..."
npx git-changelog-command-line -of CHANGELOG.md
git commit -a -m ":memo: doc: Updated CHANGELOG.md..."

git push origin master:refs/heads/master

echo "Creating GitHub release..."
gh release create v$versionNumber --notes "See CHANGELOG.md for release notes" --target master

echo "Pulling ORIGIN master into local branch..."
git pull origin

echo "Pushing master (+ tags) to ORIGIN..."
git push
git push --tags

echo "Checking out local develop branch..."
git checkout develop

echo "Pulling ORIGIN develop into local branch..."
git pull origin

echo "Merging CHANGELOG.md from master into develop..."
git merge master --no-edit -m ":twisted_rightwards_arrows: doc: merged CHANGELOG.md from master into develop branch" --strategy-option theirs

echo "Pushing develop to ORIGIN..."
git push

echo "Enabling git messages for a release again"
export GIT_MERGE_AUTOEDIT=yes

echo "Wink Affiliate WordPress plugin $versionNumber has been successfully released"
