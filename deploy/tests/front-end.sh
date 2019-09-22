#!/usr/bin/env bash

# Fetch a single business's records
BUSINESS_HTML="$(curl -s http://localhost/business/F000032)"

if ! grep -q 'AMERICAN BRANDS' <<< "$BUSINESS_HTML"; then
    echo "ERROR: Front-end is not returning business records"
    ERRORED=true
fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    exit 1
fi
