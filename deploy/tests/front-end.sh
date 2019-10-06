#!/usr/bin/env bash

# Fetch a single business's records
if [ "$(curl -s http://localhost/business/F000032 |grep -c 'AMERICAN BRANDS')" -lt 1 ]; then
    echo "ERROR: Front-end is not returning business records:"
    curl http://localhost/business/F000032
    ERRORED=true
fi

# Query a business ID that doesn't exist
if [ "$(curl -Is http://localhost/business/F000001 |grep -c '404 Not Found')" -lt 1 ]; then
    echo "ERROR: Front-end is not returning a 404 response to request for a non-existent business ID:"
    curl -Is http://localhost/business/F000001
    ERRORED=true
fi

# Run a search to verify that there are results
if [ "$(curl -s http://localhost/search/peabody |grep -c 'Riggs')" -lt 1 ]; then
    echo "ERROR: Search is not returning results:"
    curl http://localhost/search/peabody
    ERRORED=true
fi

# Run a search for a non-existent string to verify that there are no results
if [ "$(curl -s http://localhost/search/asdfghjkl |grep -c 'No results found')" -lt 1 ]; then
    echo "ERROR: Search should be reporting no results found, but is not:"
    curl http://localhost/search/asdfghjkl
    ERRORED=true
fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    exit 1
fi
