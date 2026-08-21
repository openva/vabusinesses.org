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

## Updating the data

`scripts/update.sh` downloads the SCC bulk data, cleans it, and rebuilds
`data/vabusinesses.sqlite`. It runs weekly from `deploy/crontab`.

The SCC gates bulk downloads behind a cookie-consent interstitial. Requesting the
file directly returns a `302` to `/Cookie/CookieConsent` rather than the ZIP, so
the updater first POSTs to `/Cookie/StoreCookieConsent` (with an empty body --
otherwise the server answers `411 Length Required`) to obtain a `cookiesAccepted`
cookie, then downloads using that cookie.

Note that the SCC archive no longer ships `Tables.csv`, which `load-data.sql`
imports for the code-to-description lookups. It is regenerated from the checked-in
`includes/tables.json`.

The SCC has also changed the shape of the data since this site was last updated:

* Entity IDs gained a character -- `F000032` is now `F0000325`. Both widths are
  accepted, so existing links keep working.
* Every field arrives padded with a leading tab and trailing spaces, which
  `scripts/trim-csv.php` strips. That trimming is CSV-aware; a plain
  `sed 's/ *, */,/g'` would rewrite the comma inside `"AMERICAN BRANDS, INC."`.
* `Status` and `IndustryCode` now contain descriptions (`INACTIVE`,
  `0 - General`) rather than the numeric codes the lookup tables expand, so a
  value with no lookup match is displayed as-is.
* There is a new `StatusReason` column, and three new files (`GP.csv`, `BT.csv`,
  `PSA.csv`) that nothing imports yet.

## Running tests

E2E and functional tests are in `/deploy/tests/`, and can all be run with `/deploy/tests/run-all.sh`. From outside of the Docker container, they should be invoked with `/run-tests.sh`.
