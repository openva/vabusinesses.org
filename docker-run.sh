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
WEB_ID=$(docker ps |grep vabusinesses |cut -d " " -f 1)
docker exec "$WEB_ID" /var/www/htdocs/deploy/docker-setup-site.sh

# Return to the original directory
cd "$CWD" || exit

echo "Site running at http://localhost:5000"
