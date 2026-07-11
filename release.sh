#!/usr/bin/env bash
#
# release.sh — tag a release, build a clean release tree, and package it
# as .tar.gz and .zip archives under dist/.
#
# Usage: ./release.sh <version>
# Example: ./release.sh 1.0.0

set -euo pipefail

if [ "$#" -ne 1 ]; then
    echo "Usage: $0 <version>" >&2
    echo "Example: $0 1.0.0" >&2
    exit 1
fi

VERSION="${1#v}"
TAG="v${VERSION}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="${ROOT_DIR}/dist"
RELEASE_NAME="phorum-${VERSION}"
BUILD_DIR="$(mktemp -d)"
STAGE_DIR="${BUILD_DIR}/${RELEASE_NAME}"
TAG_CREATED=0

# Files/directories that are tracked in git but have no business in a
# release tarball (dev tooling, tests, IDE config, agent instructions).
EXCLUDES=(
    tests
    docker
    docker-compose.yml
    phpunit.xml.dist
    .idea
    .github
    .claude
    .gitignore
    AGENTS.md
    CLAUDE.md
    db/sqlite_test.sql
    release.sh
)

cleanup() {
    local exit_code=$?
    rm -rf "${BUILD_DIR}"
    if [ "${exit_code}" -ne 0 ] && [ "${TAG_CREATED}" -eq 1 ]; then
        echo "Build failed — removing tag ${TAG}." >&2
        git -C "${ROOT_DIR}" tag -d "${TAG}" >/dev/null 2>&1 || true
    fi
    exit "${exit_code}"
}
trap cleanup EXIT

cd "${ROOT_DIR}"

if [ -n "$(git status --porcelain)" ]; then
    echo "Error: working tree has uncommitted changes. Commit or stash first." >&2
    exit 1
fi

if git rev-parse "${TAG}" >/dev/null 2>&1; then
    echo "Error: tag ${TAG} already exists." >&2
    exit 1
fi

echo "==> Tagging ${TAG}"
git tag -a "${TAG}" -m "Release ${VERSION}"
TAG_CREATED=1

echo "==> Exporting tagged tree"
mkdir -p "${STAGE_DIR}"
git archive "${TAG}" | tar -x -C "${STAGE_DIR}"

echo "==> Removing development-only files"
for path in "${EXCLUDES[@]}"; do
    rm -rf "${STAGE_DIR:?}/${path}"
done

echo "==> Installing production dependencies"
(cd "${STAGE_DIR}" && composer install --no-dev --optimize-autoloader --no-interaction --quiet)

mkdir -p "${DIST_DIR}"
TARBALL="${DIST_DIR}/${RELEASE_NAME}.tar.gz"
ZIPFILE="${DIST_DIR}/${RELEASE_NAME}.zip"
rm -f "${TARBALL}" "${ZIPFILE}"

echo "==> Creating ${TARBALL#"${ROOT_DIR}"/}"
tar -czf "${TARBALL}" -C "${BUILD_DIR}" "${RELEASE_NAME}"

echo "==> Creating ${ZIPFILE#"${ROOT_DIR}"/}"
(cd "${BUILD_DIR}" && zip -rq "${ZIPFILE}" "${RELEASE_NAME}")

echo
echo "Release ${VERSION} created:"
echo "  ${TARBALL}"
echo "  ${ZIPFILE}"
echo
echo "Don't forget to push the tag: git push origin ${TAG}"
