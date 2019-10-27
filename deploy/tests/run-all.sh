#!/usr/bin/env bash

# Switch to the working directory from wherever this is being invoked
pushd .
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd "$DIR" || exit

# Run the source data tests, but only if the data isn't already saved
if [ ! -f ../../data/vabusinesses.sqlite ]; then
    if ! ./source-data.sh; then
        ERRORED=true
    fi
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
    echo "Some Bash tests failed"
else
    echo "All Bash tests passed"
fi

cd "$DIR" || exit 1
cd ../.. || exit 1

pwd

./vendor/bin/phpunit --bootstrap deploy/tests/bootstrap.php -c deploy/tests/phpunit.xml --coverage-clover=coverage-report.clover --log-junit=test-report.xml

# Switch back to the directory this was invoked from
popd || exit
