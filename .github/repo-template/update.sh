#!/bin/sh

while getopts ":f" opt; do
  case $opt in
    f)
      forceUpdate=true
      ;;
    \?)
      echo "repo-template-php: Invalid option -$OPTARG" >&2
      exit 1
      ;;
  esac
done

# Exit when checkout-type is 0 (retrieving files from index)

checkoutType=$3

if [ "$checkoutType" = 0 ]; then
  exit
fi

# Force update (supplied "-f" flag) regardless of being on master-/develop-branch

if [ "$forceUpdate" != true ]; then

  branch="$(git rev-parse --abbrev-ref HEAD)"

  if [ "$branch" = "master" ] || [ "$branch" = "develop" ]; then
    echo "repo-template-php: On master-/develop-branch; skipping auto-update"
    exit
  fi

fi

echo "repo-template-php: Attempting auto-update"

git remote add repo-template-php git@github.com:studyportals/repo-template-php.git
git fetch --quiet repo-template-php 2> /dev/null

currentRevision=$(git show-ref --hash repo-template-php/master)

if [ -f .github/repo-template/revision ]; then
  usedRevision=$(tr -d '[:space:]' < .github/repo-template/revision)

  if [ "$currentRevision" != "$usedRevision" ]; then
    git stash --quiet --include-untracked

    gitApplyOutput=$(git diff "$usedRevision"..repo-template-php/master -- . \
      ':(exclude)README.md' \
      ':(exclude).github/repo-template/*' \
      ':(exclude)*.gitkeep' | git apply 2>&1)
    echo "$currentRevision" > .github/repo-template/revision

    commitTitle="Merge branch 'repo-template-php/master' into $(git rev-parse --abbrev-ref HEAD)"
    commitMessage="Was at studyportals/repo-template-php@$usedRevision"
    commitMessage="$(printf "%s\n%s" "$commitMessage," "now at studyportals/repo-template-php@$currentRevision.")"

    if [ ! -z "$gitApplyOutput" ]; then
      mergeErrorTitle="The following errors were encountered while merging:"
      mergeErrorFooter="Please manually inspect/update the files mentioned above."
      commitMessage="$(printf "%s\n\n%s\n\n%s\n\n%s" "$commitMessage" "$mergeErrorTitle" \
        "$gitApplyOutput" "$mergeErrorFooter")"
    fi

    git add .
    git commit --no-verify -e -m "$commitTitle" -m "$commitMessage"

    git stash pop --quiet

  else
    echo "repo-template-php: No updates available"
  fi

else
  echo "$currentRevision" > .github/repo-template/revision

  commitTitle="Init 'repo-template-php' automatic updates"
  commitMessage="At studyportals/repo-template-php@$currentRevision"

  git add .github/repo-template/revision
  git commit --no-verify -e -m "$commitTitle" -m "$commitMessage"
fi

git remote remove repo-template-php
