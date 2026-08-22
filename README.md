# Virginia Businesses

Website for Virginia State Corporation Commission data.

[![CI](https://github.com/openva/vabusinesses.org/actions/workflows/ci.yml/badge.svg)](https://github.com/openva/vabusinesses.org/actions/workflows/ci.yml)
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
* There is a new `StatusReason` column.
* Three entity types postdate this site: general partnerships (`GP.csv`, ~7,500
  entities), business trusts (`BT.csv`, ~1,900) and public service authorities
  (`PSA.csv`, ~130). They share `LP.csv`'s schema and their entity IDs do not
  collide with the existing tables, so they are loaded into SQLite and listed in
  `Business::ENTITY_TABLES` alongside the original three. They are treated as
  optional: if the SCC stops shipping one, the run reports it and carries on
  rather than failing.
* Entity IDs are validated structurally (one letter or digit, then six or seven
  digits) rather than against a list of known prefixes. The SCC has added
  prefixes over time -- `J` and `K` with general partnerships, `B` and `C` with
  business trusts -- and an allowlist silently 404s each new type.

## Dependencies

Composer runs inside the container, against the same PHP the site runs on, and
`./docker-run.sh` fails if it does not succeed. The repository previously carried
a `composer.phar` (Composer 1.10, 2020) that resolved against the host's PHP;
once the container moved to PHP 8, every install failed with "your requirements
could not be resolved" and the site quietly kept running on a `vendor/` directory
from 2019. Note that `vendor/` is gitignored, so a fresh clone has none at all.

Smarty, AltoRouter and PHPUnit are all held at the oldest releases that support
PHP 8 (Smarty 4 rather than 5, to avoid its template changes -- the site uses a
single template and only `assign()` and `display()`).

## Continuous integration

`.github/workflows/ci.yml` builds the site, runs the tests, scans with
SonarCloud, and deploys `master` to S3 and CodeDeploy. It replaces a Travis
configuration that had stopped running.

Three repository secrets are required (Settings > Secrets and variables >
Actions). The Travis config stored these encrypted with Travis's own key, so the
values could not be carried over -- they have to be set fresh:

| Secret | Used for |
| --- | --- |
| `AWS_ACCESS_KEY_ID` | S3 upload and CodeDeploy |
| `AWS_SECRET_ACCESS_KEY` | S3 upload and CodeDeploy |
| `SONAR_TOKEN` | SonarCloud analysis |

No secret is written into the deployment bundle. See "Server configuration"
below for the Slack webhook, which the server holds itself.

The tests that download the full SCC dataset and rebuild the database take
several minutes, so they do not run on every push. They run on the weekly
schedule, whenever there is no database to test against, and on a manual run
started with the "Also run the slow SCC download/rebuild tests" box checked.
Locally, `TEST_SOURCE_DATA=1 ./run-tests.sh` does the same.

## Server configuration

`scripts/update.sh` posts to Slack when an update fails. It reads the webhook
from `/etc/vabusinesses.env`, which lives on the server and is deliberately not
part of the deployed code:

```sh
sudo tee /etc/vabusinesses.env > /dev/null <<'ENV'
SLACK_WEBHOOK_URL="https://hooks.slack.com/services/..."
ENV
sudo chmod 600 /etc/vabusinesses.env
```

Set `VABUSINESSES_ENV` to read it from somewhere else. If the file is missing the
update still runs, and simply says so instead of notifying.

This replaces an earlier `deploy/populate-secrets.sh`, which substituted the
webhook into a git-tracked `scripts/secrets.sh` during the build. That put a live
secret into the checkout and into the deployment bundle on every release. Keeping
the secret on the server means it never enters the repository, the build, or S3 --
and rotating it no longer requires a deploy.

## Running tests

E2E and functional tests are in `/deploy/tests/`, and can all be run with `/deploy/tests/run-all.sh`. From outside of the Docker container, they should be invoked with `/run-tests.sh`.
