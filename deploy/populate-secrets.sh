#!/usr/bin/env bash

cd $(dirname "$0") || exit 1

# We use an alternate regex delimiter because the webhook URL contains slashes
sed -i "s|{SLACK_WEBHOOK_URL}|$SLACK_WEBHOOK|g" ../scripts/secrets.sh
