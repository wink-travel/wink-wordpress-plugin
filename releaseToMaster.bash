#!/bin/bash

#
# Copyright (c) wink.travel 2022.
#

echo "Releasing new version of Wink Affiliate WordPress plugin using git flow..."

versionNumber=$(npx git-changelog-command-line --print-next-version --major-version-pattern BREAKING --minor-version-pattern feat)

read -p "Do you want to proceed with version $versionNumber? (y/n) " yn

case $yn in
[yY])
  echo "Disabling git messages for a release"
  export GIT_MERGE_AUTOEDIT=no

  git cliff --unreleased --tag $versionNumber --sort newest --prepend CHANGELOG.md

  echo "Committing version changes for $versionNumber"
  sed -i '' 's/Version.*/Version: $versionNumber/g' README.txt
  sed -i '' 's/Stable tag.*/Stable tag: $versionNumber/g' README.txt
  sed -i '' 's/Version.*/Version:     $versionNumber/g' wink.php

  git commit -a -m "build: arrow_up: bumping version and merging to master

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
  gh release create $versionNumber --notes "See CHANGELOG.md for release notes" --target master

  echo "Pulling ORIGIN master into local branch..."
  git pull origin

  echo "Pushing master (+ tags) to ORIGIN..."
  git push

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
  ;;
[nN])
  echo "Exiting..."
  exit
  ;;
*)
  echo "Invalid response"
  exit 1
  ;;
esac
