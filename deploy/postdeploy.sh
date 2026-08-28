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
# This deliberately does not use "aws s3 sync". Sync applies --exclude and
# --include on the client, so it first enumerates every object in the bucket --
# which for a bucket of any size takes minutes before a byte is transferred, and
# was long enough to time out the deployment. Addressing the one key directly
# needs no listing at all.
#
# head-object is a single cheap request that reports the object's size and
# modification time, which is enough to decide whether the local copy is already
# current. Only then is the ~100 MB actually fetched.
#
# A failure here is deliberately not fatal. The cache is optional -- without it
# the site renders without maps -- and a transient S3 problem should not roll
# back an otherwise good release.
GEOCODE_CACHE="$WEBROOT/data/addresses.db"
GEOCODE_BUCKET="data.vabusinesses.org"
GEOCODE_KEY="addresses.db"

mkdir -p "$WEBROOT/data"

REMOTE=$(aws s3api head-object \
    --bucket "$GEOCODE_BUCKET" \
    --key "$GEOCODE_KEY" \
    --query "[ContentLength, LastModified]" \
    --output text 2>/dev/null)

if [[ -z "$REMOTE" ]]; then
    if [[ -f "$GEOCODE_CACHE" ]]; then
        echo "Note: could not reach the geocode cache in S3; keeping the existing copy" >&2
    else
        echo "Note: no geocode cache available; business pages will render without maps" >&2
    fi
else
    REMOTE_BYTES=${REMOTE%%[[:space:]]*}
    LOCAL_BYTES=$(stat -c %s "$GEOCODE_CACHE" 2>/dev/null || echo 0)

    if [[ "$REMOTE_BYTES" == "$LOCAL_BYTES" ]]; then
        echo "Geocode cache is current ($(du -h "$GEOCODE_CACHE" | cut -f1))"
    elif aws s3 cp "s3://$GEOCODE_BUCKET/$GEOCODE_KEY" "$GEOCODE_CACHE" --only-show-errors; then
        echo "Geocode cache updated ($(du -h "$GEOCODE_CACHE" | cut -f1))"
    else
        if [[ -f "$GEOCODE_CACHE" ]]; then
            echo "Note: could not refresh the geocode cache; keeping the existing copy" >&2
        else
            echo "Note: no geocode cache available; business pages will render without maps" >&2
        fi
    fi
fi

# Give the web server user ownership over all files. Note the trailing "." and
# not "./*": the glob skips dotfiles, which left .htaccess -- the file that
# drives all of the site's routing -- owned by whoever CodeDeploy ran as.
cd "$WEBROOT" || exit 1
chown -R www-data .
chgrp -R ubuntu .
