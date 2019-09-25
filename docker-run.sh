#!/bin/bash

# Save the current directory, to return to at the end
CWD=$(pwd)

# Change to the directory that this script is in
cd $(dirname "$0") || exit

# Stand it up
docker-compose build && docker-compose up -d

# Run the site setup script
WEB_ID=$(docker ps |grep vabusinesses |cut -d " " -f 1)
docker exec "$WEB_ID" /var/www/htdocs/deploy/docker-setup-site.sh

# Return to the original directory
cd "$CWD" || exit

echo "Site running at http://localhost:5000"
