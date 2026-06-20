#!/usr/bin/env bash
#
# Cut a new release of flashmandu/app-sdk.
#
#   bin/release.sh 1.0.2
#
# Tags the current master HEAD as vX.Y.Z and pushes branch + tag. The Release
# GitHub Action then runs tests, creates the GitHub release, and notifies
# Packagist so the version is published automatically.
set -euo pipefail

VERSION="${1:-}"
if [ -z "${VERSION}" ]; then
  echo "Usage: $0 <version>   e.g. $0 1.0.2" >&2
  exit 1
fi

VERSION="${VERSION#v}"
TAG="v${VERSION}"

if ! [[ "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Version must be semver (X.Y.Z), got: ${VERSION}" >&2
  exit 1
fi

if [ -n "$(git status --porcelain)" ]; then
  echo "Working tree is dirty — commit or stash changes before releasing." >&2
  exit 1
fi

if git rev-parse "${TAG}" >/dev/null 2>&1; then
  echo "Tag ${TAG} already exists." >&2
  exit 1
fi

git tag -a "${TAG}" -m "${TAG}"
git push origin HEAD
git push origin "${TAG}"

echo "Pushed ${TAG}. The Release workflow will publish it to GitHub + Packagist."
