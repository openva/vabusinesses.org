#!/usr/bin/env bash

# AMERICAN BRANDS, INC. is our fixture record. The SCC lengthened entity IDs at
# some point (F000032 became F0000325), so discover which form this dataset uses
# rather than pinning the test to one vintage of the data.
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
    # Fetch a single business's records, compare results
    BUSINESS_JSON="$(curl -s "http://localhost/api/business/$ENTITY_ID")"

    if [ "$(echo "$BUSINESS_JSON" | jq -r '.EntityID')" != "$ENTITY_ID" ]; then
        echo "ERROR: API is not returning EntityID correctly"
        echo "$BUSINESS_JSON"
        ERRORED=true
    fi

    if [ "$(echo "$BUSINESS_JSON" | jq -r '.Name')" != 'AMERICAN BRANDS, INC.' ]; then
        echo "ERROR: API is not returning Name correctly"
        echo "$BUSINESS_JSON"
        ERRORED=true
    fi

    if [ "$(echo "$BUSINESS_JSON" | jq -r '.IncorpDate')" != '1903-08-18' ]; then
        echo "ERROR: API is not returning IncorpDate correctly"
        echo "$BUSINESS_JSON"
        ERRORED=true
    fi

    # The stock description is worded differently across dataset vintages
    # ("COMMON (200000000)" vs. "Class A"), so just require that it is populated.
    if [ -z "$(echo "$BUSINESS_JSON" | jq -r '.Stock1 // empty')" ]; then
        echo "ERROR: API is not returning Stock1 correctly"
        echo "$BUSINESS_JSON"
        ERRORED=true
    fi
fi

# Run a search for a test query. The exact number of matches grows as businesses
# register, so assert that the search works and respects its cap rather than
# hard-coding a count that any data refresh invalidates.
SEARCH_COUNT="$(curl -s http://localhost/api/search/test | jq '. | length')"

if [ "$SEARCH_COUNT" -lt 1 ]; then
    echo "ERROR: API is returning no search results for 'test'"
    ERRORED=true
fi

# 33 rows per table across corp, llc and lp.
if [ "$SEARCH_COUNT" -gt 99 ]; then
    echo "ERROR: API is returning $SEARCH_COUNT search results, above the 99 cap"
    ERRORED=true
fi

# Run a search for a test query that will fail
SEARCH_JSON="$(curl -s http://localhost/api/search/asdflasdfqasdl)"

if [ "$(echo "$SEARCH_JSON" | jq '. | length')" -ne '0' ]; then
    echo "ERROR: API is returning excessive search results"
    echo "$SEARCH_JSON"
    ERRORED=true
fi

# A search for SQL metacharacters must be treated as a literal string. Before
# these queries were parameterized, this returned the whole 99-row cap.
INJECTION_JSON="$(curl -s --get --data-urlencode 'q=" OR 1=1 --' http://localhost/search/)"

if [ "$(echo "$INJECTION_JSON" | grep -c 'No results found')" -lt 1 ]; then
    echo "ERROR: a SQL injection payload returned results instead of being treated as a literal string"
    ERRORED=true
fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    exit 1
fi
