#!/usr/bin/env bash

# Run by CodeDeploy at AfterInstall. Anything that fails here should fail the
# deployment rather than leave the server half-configured.
set -e

WEBROOT="/var/www/vabusinesses.org"

cd "$WEBROOT/deploy/" || exit 1

# Set up the crontab. The file holds __WEBROOT__ as a placeholder rather than a
# literal path, so that the webroot is defined once, above. cron cannot be given
# a relative path: it runs jobs from the user's home directory, not from the
# directory the crontab was installed from.
CRONTAB=$(mktemp)
sed "s|__WEBROOT__|$WEBROOT|g" crontab > "$CRONTAB"

if grep -q "__WEBROOT__" "$CRONTAB"; then
    echo "ERROR: failed to substitute the webroot into the crontab." >&2
    rm -f "$CRONTAB"
    exit 1
fi

crontab "$CRONTAB"
rm -f "$CRONTAB"
echo "Installed crontab:"
crontab -l | grep -v '^#'

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

if ! command -v aws > /dev/null; then
    # For fetching the geocode cache from S3, below.
    packages+=("awscli")
fi

if [[ ${#packages[@]} -gt 0 ]]; then
    echo "Installing: ${packages[*]}"
    apt-get update -qq
    apt-get install -y --no-install-recommends "${packages[@]}"
else
    echo "All required packages are already installed."
fi

# Fetch the geocode cache that the business pages draw their maps from.
#
# It is not in the deployment bundle: it is ~100 MB, is regenerated
# independently of this repository by the geocoder, and is gitignored.
#
# "s3 sync" rather than "s3 cp" so that the ~100 MB is only transferred when the
# object in S3 is newer or a different size than the local copy -- an unchanged
# cache costs one HEAD request. The --exclude/--include pair is how sync is
# limited to a single object.
#
# A failure here is deliberately not fatal. The cache is optional -- without it
# the site renders without maps -- and a transient S3 problem should not roll
# back an otherwise good release.
GEOCODE_CACHE="$WEBROOT/data/addresses.db"

mkdir -p "$WEBROOT/data"

if aws s3 sync "s3://data.vabusinesses.org/" "$WEBROOT/data/" \
    --exclude "*" --include "addresses.db" --only-show-errors; then
    if [[ -f "$GEOCODE_CACHE" ]]; then
        echo "Geocode cache present ($(du -h "$GEOCODE_CACHE" | cut -f1))"
    else
        echo "Note: no addresses.db in the bucket; pages will render without maps" >&2
    fi
else
    if [[ -f "$GEOCODE_CACHE" ]]; then
        echo "Note: could not refresh the geocode cache; keeping the existing copy" >&2
    else
        echo "Note: no geocode cache available; business pages will render without maps" >&2
    fi
fi

# Give the web server user ownership over all files. Note the trailing "." and
# not "./*": the glob skips dotfiles, which left .htaccess -- the file that
# drives all of the site's routing -- owned by whoever CodeDeploy ran as.
cd "$WEBROOT" || exit 1
chown -R www-data .
chgrp -R ubuntu .
