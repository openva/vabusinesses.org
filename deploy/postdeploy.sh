#!/usr/bin/env bash

# Run by CodeDeploy at AfterInstall. Anything that fails here should fail the
# deployment rather than leave the server half-configured.
set -e

WEBROOT="/vol/vabusinesses.org/htdocs"

cd "$WEBROOT/deploy/" || exit 1

# Set up the crontab
crontab deploy

# Enable the SQLite extension
if [[ "$(dpkg -l |grep php |grep -c sqlite)" -lt 1 ]]; then
    apt-get install -y php5-sqlite
fi

# Enable the SQLite extension
if [[ "$(dpkg -l |grep npm)" -lt 1 ]]; then
    apt-get install -y npm
fi

# Give the web server user ownership over all files. Note the trailing "." and
# not "./*": the glob skips dotfiles, which left .htaccess -- the file that
# drives all of the site's routing -- owned by whoever CodeDeploy ran as.
cd "$WEBROOT" || exit 1
chown -R www-data .
chgrp -R ubuntu .
