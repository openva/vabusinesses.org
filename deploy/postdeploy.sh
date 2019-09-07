#!/usr/bin/env bash

cd /vol/vabusinesses.org/htdocs/

# Set up the crontab
crontab deploy/crontab

# Enable the SQLite extension
if [ "$(dpkg -l |grep php |grep -c sqlite)" -lt 1 ]; then
    apt-get install php5-sqlite
fi
