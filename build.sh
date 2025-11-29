#!/bin/bash

# WordPress Plugin Build Script
# Creates production zip file for WordPress plugin installation in /build/

set -e

PLUGIN_SLUG="chubes-docs"
BUILD_DIR="build"

echo "Building ${PLUGIN_SLUG}..."

rm -rf "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}"

composer install --no-dev --optimize-autoloader --no-interaction

rsync -av --exclude-from='.buildignore' ./ "${BUILD_DIR}/${PLUGIN_SLUG}/"

REQUIRED_FILES=(
    "${BUILD_DIR}/${PLUGIN_SLUG}/${PLUGIN_SLUG}.php"
    "${BUILD_DIR}/${PLUGIN_SLUG}/vendor/autoload.php"
    "${BUILD_DIR}/${PLUGIN_SLUG}/inc/Api/Routes.php"
    "${BUILD_DIR}/${PLUGIN_SLUG}/inc/Fields/RepositoryFields.php"
    "${BUILD_DIR}/${PLUGIN_SLUG}/inc/Sync/SyncManager.php"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -f "$file" ]; then
        echo "ERROR: Required file missing: $file"
        exit 1
    fi
done

cd "${BUILD_DIR}"
zip -r "${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}"
cd ..

rm -rf "${BUILD_DIR}/${PLUGIN_SLUG}"

composer install --no-interaction

echo "✅ Build complete: ${BUILD_DIR}/${PLUGIN_SLUG}.zip"
