#!/usr/bin/env bash

cd $(dirname "$0") || exit 1

# We use an alternate regex delimiter because the webhook URL contains slashes
sed -i "s|{SLACK_WEBHOOK_URL}|$SLACK_WEBHOOK|g" ../scripts/secrets.sh

# There's an exit statement at the lead of the secrets file, which we use to
# keep secrets from populating, except when deployed. Since we're deploying,
# remove that exit statement
sed -i "s|return||g" ../scripts/secrets.sh 
