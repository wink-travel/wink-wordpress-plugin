#!/bin/bash

#
# Copyright (c) iko.travel 2022.
#

echo "Disabling git messages for a release"
export GIT_MERGE_AUTOEDIT=no

echo "Releasing new version of iko-travel-affiliate WordPress plugin with git flow..."
echo "Enter version number. E.g. 1.2.3";

read versionNumber
versionNumber = "v" + $versionNumber;

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

echo "Pulling ORIGIN master into local branch..."
git pull origin

echo "Pushing master (+ tags) to ORIGIN..."
git push
git push --tags

echo "Checking out local develop branch..."
git checkout develop

echo "Pulling ORIGIN develop into local branch..."
git pull origin

echo "Pushing develop to ORIGIN..."
git push

echo "Enabling git messages for a release again"
export GIT_MERGE_AUTOEDIT=yes

echo "iko-travel-affiliate WordPress plugin v$versionNumber has been successfully released"
