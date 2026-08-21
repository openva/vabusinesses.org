#!/bin/bash

# Save the current directory, to return to at the end
CWD=$(pwd)

# Change to the directory that this script is in
cd "$(dirname "$0")" || exit

# Install Composer, if it's not installed
hash php composer.phar 2>/dev/null || {
    wget https://raw.githubusercontent.com/composer/getcomposer.org/76a7060ccb93902cd7576b67264ad91c8a2700e2/web/installer -O - -q | php -- --quiet
}

# Install Composer dependencies
php composer.phar install

# Stand it up
docker compose build && docker compose up -d

# Run the site setup script
WEB_ID=$(docker ps -q --filter name=vabusinesses)
if [ -z "$WEB_ID" ]; then
    echo "ERROR: the vabusinesses container is not running." >&2
    cd "$CWD" || exit
    exit 1
fi
docker exec "$WEB_ID" /var/www/htdocs/deploy/docker-setup-site.sh

# Confirm the site actually answers, rather than assuming it does. Note that
# macOS runs AirPlay Receiver on port 5000, which will intercept requests and
# return 403 before they ever reach Apache; set WEB_PORT to avoid a collision.
URL="http://localhost:${WEB_PORT:-5001}/"
STATUS=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$URL" || echo "000")

# Return to the original directory
cd "$CWD" || exit

if [ "$STATUS" = "200" ]; then
    echo "Site running at $URL"
else
    echo "ERROR: $URL returned HTTP $STATUS (expected 200)." >&2
    if [ "$STATUS" = "403" ]; then
        echo "       Another service may hold this port. On macOS, disable" >&2
        echo "       AirPlay Receiver or re-run with: WEB_PORT=5002 ./docker-run.sh" >&2
    fi
    echo "       Container logs: docker logs vabusinesses" >&2
    exit 1
fi
