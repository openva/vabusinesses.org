# Virginia Businesses

Website for Virginia State Corporation Commission data.

[![Build Status](https://travis-ci.org/openva/vabusinesses.org.svg?branch=master)](https://travis-ci.org/openva/vabusinesses.org)
[![Dependency Vulnerability Analysis](https://app.snyk.io/test/github/openva/vabusinesses.org/badge.svg?targetFile=package.json)](https://app.snyk.io/test/github/openva/vabusinesses.org?targetFile=package.json)

## Running locally

`./docker-run.sh` to start, `./docker-stop.sh` to stop. The site is served at
http://localhost:5001/.

To use a different port, set `WEB_PORT`:

```sh
WEB_PORT=5002 ./docker-run.sh
```

Note that port 5000 is deliberately avoided: macOS runs AirPlay Receiver there,
which intercepts requests and answers `403 Forbidden` before they ever reach
Apache. (It can also be turned off in System Settings › General › AirDrop &
Handoff.)

## Running tests

E2E and functional tests are in `/deploy/tests/`, and can all be run with `/deploy/tests/run-all.sh`. From outside of the Docker container, they should be invoked with `/run-tests.sh`.
