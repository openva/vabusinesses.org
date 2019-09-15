#!/usr/bin/env bash

# We use an alternate regex delimiter because the webhook URL contains slashes
sed -i "s|{SLACK_WEBHOOK_URL}|$SLACK_WEBHOOK|g" ../scripts/secrets.sh
