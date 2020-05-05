#!/usr/bin/env bash

GREEN='\033[0;32m'
RED='\033[0;31m'
NO_COLOR='\033[0m'

echo 'Creating a release zip file of the latest plugin version.'
echo 'The zip can be uploaded directly to the Shopware store.'

# Get current directory of the script
ROOT_DIR=$( cd "$(dirname "${BASH_SOURCE[0]}")" ; pwd -P )

# Extract version from the plugin.xml file
VERSION_RAW="$(jq -r '.version' composer.json)"

# Trim the whitespaces from the version otherwise it would cause problems
# in creating the archive zip file
VERSION="$(echo -e "${VERSION_RAW}" | tr -d '[:space:]')"
echo "Version: ${VERSION}"

STASH=$(git stash)

echo ${STASH}

git fetch --all --tags --prune

TAG=$(git tag -l "${VERSION}")

# Get current working branch
BRANCH=$(git branch | grep \* | cut -d ' ' -f2)

# If tag is available we proceed with the checkout and zip commands
# else exit with code 1
if [[ -z "${TAG}" ]]; then
    echo -e "${RED}[ERROR]: Tag ${TAG} not found"
    exit 1
fi

git checkout tags/${TAG}

# Copying plugins files
echo "Copying files ... "
cp -rf . /tmp/FinSearch

# Get into the created directory for running the archive command
cd /tmp/FinSearch

echo "Running composer install..."
# Install dependencies
composer install --no-dev --optimize-autoloader --prefer-dist

cd /tmp

ZIP_FILE_STORE="${ROOT_DIR}/FinSearch-${VERSION}-store.zip"
ZIP_FILE_MANUAL_INSTALL="${ROOT_DIR}/FinSearch-${VERSION}-manual-install.zip"
# Run archive command to create the zip in the root directory
zip -r9 ${ZIP_FILE_STORE} FinSearch -x FinSearch/phpunit.xml.dist FinSearch/phpcs.xml FinSearch/tests/\* \
 FinSearch/archive.sh FinSearch/.gitignore FinSearch/.travis.yml FinSearch/.idea/\* FinSearch/.git/\* *.zip \
 FinSearch/composer.lock FinSearch/README.md FinSearch/vendor/\*

# Remove dependencies that are not required for a normal build.
composer remove shopware/core shopware/storefront

zip -r9 ${ZIP_FILE_MANUAL_INSTALL} FinSearch -x FinSearch/phpunit.xml.dist FinSearch/phpcs.xml FinSearch/tests/\* \
 FinSearch/archive.sh FinSearch/.gitignore FinSearch/.travis.yml FinSearch/.idea/\* FinSearch/.git/\* *.zip \
 FinSearch/composer.lock

# Delete the directory after script execution
rm -rf '/tmp/FinSearch'

cd ${ROOT_DIR}

echo 'Restoring work in progress ...'

git checkout ${BRANCH}

# Only apply stash if there are some local changes
if [[ ${STASH} != 'No local changes to save' ]]; then
    git stash pop
fi

echo -e "${GREEN}Release was successfully created! Location: '${ZIP_FILE}'"
