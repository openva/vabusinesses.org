#!/usr/bin/env bash

# Fetch a single business's records
if $("curl -s http://localhost/business/F000032 |grep -c 'AMERICAN BRANDS'") -lt 1; then
    echo "ERROR: Front-end is not returning business records"
    ERRORED=true
fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    exit 1
fi
