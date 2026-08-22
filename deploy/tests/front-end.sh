#!/usr/bin/env bash

# Entity IDs gained a character when the SCC changed their export format
# (F000032 became F0000325), so find the fixture in whichever form this dataset
# uses instead of pinning the test to one vintage.
ENTITY_ID=""
for candidate in F0000325 F000032; do
    if [ "$(curl -s "http://localhost/api/business/$candidate" | jq -r '.EntityID? // empty')" == "$candidate" ]; then
        ENTITY_ID="$candidate"
        break
    fi
done

if [ -z "$ENTITY_ID" ]; then
    echo "ERROR: fixture record (AMERICAN BRANDS, INC.) not found as F0000325 or F000032"
    ERRORED=true
else
    # Fetch a single business's records
    if [ "$(curl -s "http://localhost/business/$ENTITY_ID" | grep -c 'AMERICAN BRANDS')" -lt 1 ]; then
        echo "ERROR: Front-end is not returning business records:"
        curl -s "http://localhost/business/$ENTITY_ID"
        ERRORED=true
    fi
fi

# Query a business ID that is syntactically valid but does not exist
for missing in F0000019 F000001; do
    if [ "$(curl -Is "http://localhost/business/$missing" |grep -c '404 Not Found')" -lt 1 ]; then
        echo "ERROR: Front-end is not returning a 404 response to request for a non-existent business ID:"
        curl -Is "http://localhost/business/$missing"
        ERRORED=true
    fi
done

# Run a search to verify that there are results
if [ "$(curl -s http://localhost/search/\?q=peabody |grep -c 'Riggs')" -lt 1 ]; then
    echo "ERROR: Search is not returning results:"
    curl -s http://localhost/search/\?q=peabody
    ERRORED=true
fi

# Run a search for a non-existent string to verify that there are no results
if [ "$(curl -s http://localhost/search/\?q=asdfghjkl |grep -c 'No results found')" -lt 1 ]; then
    echo "ERROR: Search should be reporting no results found, but is not:"
    curl -s http://localhost/search/\?q=asdfghjkl
    ERRORED=true
fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    exit 1
fi
