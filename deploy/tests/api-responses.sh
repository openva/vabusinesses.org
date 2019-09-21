#!/usr/bin/env bash

# Fetch a single business's records, compare results
BUSINESS_JSON="$(curl -s http://localhost:5000/api/business/F000032)"

if [ "$(echo $BUSINESS_JSON |jq '.EntityID')" != '"F000032"' ]; then
    echo "ERROR: API is not returning entity ID correctly"
    ERRORED=true
fi

if [ "$(echo $BUSINESS_JSON |jq '.Stock1')" != '"COMMON (200000000)"' ]; then
    echo "ERROR: API is not returning Stock1 correctly"
    ERRORED=true
fi

if [ "$(echo $BUSINESS_JSON |jq '.IncorpDate')" != '"1903-08-18"' ]; then
    echo "ERROR: API is not returning incorporation date correctly"
    ERRORED=true
fi

if [ "$(echo $BUSINESS_JSON |jq '.Name')" != '"AMERICAN BRANDS, INC."' ]; then
    echo "ERROR: API is not returning corporation name correctly"
    ERRORED=true
fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    exit 1
fi
