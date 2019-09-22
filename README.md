# Virginia Businesses

Website for Virginia State Corporation Commission data.

[![Build Status](https://travis-ci.org/openva/vabusinesses.org.svg?branch=master)](https://travis-ci.org/openva/vabusinesses.org)
[![Dependency Vulnerability Analysis](https://app.snyk.io/test/github/openva/vabusinesses.org/badge.svg?targetFile=package.json)](https://app.snyk.io/test/github/openva/vabusinesses.org?targetFile=package.json)

## Running locally

`./docker-run.sh` to start, `./docker-stop.sh` to stop.

## Running tests

E2E and functional tests are in `/deploy/tests/`, and can all be run with `/deploy/tests/run-all.sh`. From outside of the Docker container, they should be invoked with `/run-tests.sh`.
