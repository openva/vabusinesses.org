#!/usr/bin/env bash

# Run by CodeDeploy at AfterInstall. Anything that fails here should fail the
# deployment rather than leave the server half-configured.
set -e

WEBROOT="/vol/vabusinesses.org/htdocs"

cd "$WEBROOT/deploy/" || exit 1

# Set up the crontab
crontab deploy

# Install what the site and the weekly updater need, if it is not already here.
# These test for the capability rather than parsing "dpkg -l": the previous
# version compared a package-description string with -lt, which bash rejects as
# a syntax error, so the check silently did nothing.
declare -a packages=()

if ! php -m | grep -qx sqlite3; then
    packages+=("php-sqlite3")
fi

if ! command -v sqlite3 > /dev/null; then
    packages+=("sqlite3")
fi

if ! command -v jq > /dev/null; then
    # scripts/update.sh uses jq to build its Slack payload.
    packages+=("jq")
fi

if ! command -v unzip > /dev/null; then
    packages+=("unzip")
fi

if ! command -v npm > /dev/null; then
    packages+=("npm")
fi

if [[ ${#packages[@]} -gt 0 ]]; then
    echo "Installing: ${packages[*]}"
    apt-get update -qq
    apt-get install -y --no-install-recommends "${packages[@]}"
else
    echo "All required packages are already installed."
fi

# Give the web server user ownership over all files. Note the trailing "." and
# not "./*": the glob skips dotfiles, which left .htaccess -- the file that
# drives all of the site's routing -- owned by whoever CodeDeploy ran as.
cd "$WEBROOT" || exit 1
chown -R www-data .
chgrp -R ubuntu .
