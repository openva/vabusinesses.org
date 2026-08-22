#!/usr/bin/env bash

# Switch to the working directory from wherever this is being invoked
pushd . > /dev/null
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd "$DIR" || exit

# Run the source-data tests, which download the full dataset from the SCC and
# rebuild the database. That takes several minutes and depends on the SCC being
# reachable, so it is opt-in: set TEST_SOURCE_DATA=1 to include it. It still runs
# when there is no database at all, since the tests below need one.
if [ "${TEST_SOURCE_DATA:-0}" = "1" ] || [ ! -f ../../data/vabusinesses.sqlite ]; then
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

if [ "$ERRORED" == true ]; then
    echo "Some Bash tests failed"
else
    echo "All Bash tests passed"
fi

cd "$DIR" || exit 1
cd ../.. || exit 1

if ! ./vendor/bin/phpunit --bootstrap deploy/tests/bootstrap.php -c deploy/tests/phpunit.xml --coverage-clover=coverage-report.clover --log-junit=test-report.xml; then
    ERRORED=true
fi

# Switch back to the directory this was invoked from
popd > /dev/null || exit

# Report failure from either suite. Previously this script exited with whatever
# PHPUnit returned, so a failing Bash test printed its error and still reported
# success -- which would leave CI green over a real failure.
if [ "$ERRORED" == true ]; then
    exit 1
fi
