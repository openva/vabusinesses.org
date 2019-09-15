#!/usr/bin/env bash

cd /vol/vabusinesses.org/htdocs/deploy/

# Set up the crontab
crontab deploy

# Enable the SQLite extension
if [ "$(dpkg -l |grep php |grep -c sqlite)" -lt 1 ]; then
    apt-get install php5-sqlite
fi

# Save Travis CI secrets to a file
./populate-secrets.sh
