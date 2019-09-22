#!/usr/bin/env bash

# Switch to the working directory from wherever this is being invoked
pushd .
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd "$DIR" || exit

# Run the source data tests
if ! ./source-data.sh; then
    ERRORED=true
fi

# Run the API responses tests
if ! ./api-responses.sh; then
    ERRORED=true
fi

# Run the front-end tests
if ! ./front-end.sh; then
    ERRORED=true
fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    echo "Some tests failed"
    popd || exit 1
fi

echo "All tests passed"

# Switch back to the directory this was invoked from
popd || exit
