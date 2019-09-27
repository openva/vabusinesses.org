#!/usr/bin/env bash

# Fetch a single business's records
if $("curl -s http://localhost/business/F000032 |grep -c 'AMERICAN BRANDS'") -lt 1; then
    echo "ERROR: Front-end is not returning business records"
    ERRORED=true
fi

# Query a business ID that doesn't exist
if [ "$(curl -Is http://localhost/business/F000001 |grep -c '404 Not Found')" -lt 1 ]; then
    echo "ERROR: Front-end is not returning a 404 response to request for a non-existent business ID"
    ERRORED=true
fi

# Run a search to verify that there are results
if $("curl -s http://localhost/search.php?query=peabody |grep -c 'Riggs'") -lt 1; then
    echo "ERROR: Search is not returning results"
    ERRORED=true
fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    exit 1
fi
