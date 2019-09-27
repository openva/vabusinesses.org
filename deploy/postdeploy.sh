#!/usr/bin/env bash

cd /vol/vabusinesses.org/htdocs/deploy/ || exit

# Set up the crontab
crontab deploy

# Enable the SQLite extension
if [ "$(dpkg -l |grep php |grep -c sqlite)" -lt 1 ]; then
    apt-get install -y php5-sqlite
fi

# Enable the SQLite extension
if [ "$(dpkg -l |grep npm)" -lt 1 ]; then
    apt-get install -y npm
fi

# Save Travis CI secrets to a file
./populate-secrets.sh

# Give the web server user ownership over all files
cd ..
chown www-data ./*
chgrp ubuntu ./*
