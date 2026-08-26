# Virginia Businesses

Website for Virginia State Corporation Commission data.

[![CI](https://github.com/openva/vabusinesses.org/actions/workflows/ci.yml/badge.svg)](https://github.com/openva/vabusinesses.org/actions/workflows/ci.yml)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=openva_vabusinesses.org&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=openva_vabusinesses.org)
[![Dependency Vulnerability Analysis](https://app.snyk.io/test/github/openva/vabusinesses.org/badge.svg?targetFile=package.json)](https://app.snyk.io/test/github/openva/vabusinesses.org?targetFile=package.json)

## Contents

- [Running locally](#running-locally)
- [Running tests](#running-tests)
- [Architecture](#architecture)
- [Updating the data](#updating-the-data)
- [Deployment](#deployment)
  - [Repository secrets](#repository-secrets)
  - [Server configuration](#server-configuration)
- [Dependencies](#dependencies)
- [Background](#background) — why some of the odder choices are the way they are

## Running locally

```sh
./docker-run.sh     # start; serves http://localhost:5001/
./docker-stop.sh    # stop
```

`docker-run.sh` builds the image, installs Composer dependencies inside the
container, and checks that the site answers with a `200` before reporting
success.

To use a different port, set `WEB_PORT`:

```sh
WEB_PORT=5002 ./docker-run.sh
```

Port 5000 is deliberately avoided: macOS runs AirPlay Receiver there, which
intercepts requests and answers `403 Forbidden` before they ever reach Apache.
(It can also be turned off in System Settings › General › AirDrop & Handoff.)

## Running tests

```sh
./run-tests.sh                        # from outside the container
TEST_SOURCE_DATA=1 ./run-tests.sh     # also test the SCC download and rebuild
```

End-to-end and functional tests live in `deploy/tests/`. Inside the container
they can be run directly with `deploy/tests/run-all.sh`, which runs the shell
tests and then PHPUnit.

The source-data tests download the full SCC dataset and rebuild the database,
which takes several minutes and depends on the SCC being reachable. They are
therefore skipped unless `TEST_SOURCE_DATA=1` is set, or there is no database to
test against.

## Architecture

Every page fetches its data from this site's own public API over HTTP, rather
than querying the database directly. That is on purpose: the website is a
consumer of the same API third parties use, so a regression in the API breaks the
website too, and gets noticed straight away. It should stay that way.

Because the server makes those requests to itself, it has to be able to reach
itself. The request is addressed to this site *by name* — the server hosts
several sites, and Apache needs the hostname to route to the right virtual host;
requesting the server's IP address lands on whichever vhost is the default — but
the connection is pinned to `127.0.0.1` with `CURLOPT_RESOLVE`, so it never
leaves the machine.

That hostname is checked against `$known_hosts` in `includes/header.php`. Add any
alias the site is served under; the first entry is the canonical one. The check
matters: `SERVER_NAME` reflects the client's `Host` header unless Apache sets
`UseCanonicalName On`, so an unvalidated value there would let a request carrying
`Host: 169.254.169.254` redirect the server's own API calls to an address of the
client's choosing.

Set `VABUSINESSES_API_URL` (for example `http://127.0.0.1:8080`) to bypass all of
that and name the API's address explicitly.

Business records live in six SQLite tables — `corp`, `llc`, `lp`, `gp`, `bt` and
`psa` — listed in `Business::ENTITY_TABLES`. Entity IDs do not collide between
them, so a lookup by ID tries each in turn rather than inferring the table from
the ID's first character.

## Updating the data

`scripts/update.sh` downloads the SCC bulk data, cleans it, and rebuilds
`data/vabusinesses.sqlite`. It runs weekly from `deploy/crontab`.

The SCC gates bulk downloads behind a cookie-consent interstitial: requesting the
file directly returns a `302` to `/Cookie/CookieConsent` rather than the ZIP. The
updater first POSTs to `/Cookie/StoreCookieConsent` — with an empty body, or the
server answers `411 Length Required` — to obtain a `cookiesAccepted` cookie, then
downloads using it.

The archive no longer ships `Tables.csv`, which `load-data.sql` imports for the
code-to-description lookups, so it is regenerated from the checked-in
`includes/tables.json`.

### Quirks of the SCC data

The shape of the data has changed since this site was first written:

- **Entity IDs gained a character.** `F000032` is now `F0000325`. Both widths are
  accepted, so existing links keep working.
- **IDs are validated structurally** — one letter or digit, then six or seven
  digits — rather than against a list of known prefixes. The SCC has added
  prefixes over time (`J` and `K` with general partnerships, `B` and `C` with
  business trusts), and an allowlist silently 404s each new type.
- **Every field arrives padded** with a leading tab and trailing spaces, which
  `scripts/trim-csv.php` strips. That trimming is CSV-aware; a plain
  `sed 's/ *, */,/g'` would rewrite the comma inside `"AMERICAN BRANDS, INC."`.
- **`Status` and `IndustryCode` now hold descriptions** (`INACTIVE`,
  `0 - General`) rather than the numeric codes the lookup tables expand, so a
  value with no lookup match is shown as-is.
- **There is a new `StatusReason` column.**
- **Registered agent localities are FIPS codes.** `RA-Loc` holds Virginia county
  and city FIPS codes, but the SCC's own lookup table (`TableID` 05) stops at
  code 901 and covers only a seventh of the codes in the data. The rest are
  resolved from `includes/localities.json`, generated from the Census TIGER file
  in `municipalities.geojson` by `scripts/build-localities.php`. Between the two,
  88% of records resolve to a name; the remainder are an SCC-internal 970-999
  block for unincorporated communities, and are omitted rather than shown as a
  bare number.
- **Three entity types postdate this site**: general partnerships (`GP.csv`,
  ~7,500 entities), business trusts (`BT.csv`, ~1,900) and public service
  authorities (`PSA.csv`, ~130). They share `LP.csv`'s schema and are loaded
  alongside the original three. They are treated as optional: if the SCC stops
  shipping one, the run says so and carries on rather than failing.

## Deployment

`.github/workflows/ci.yml` builds the site, runs the tests, and deploys `master`
to S3 and CodeDeploy.

Code quality is analysed by SonarCloud's Automatic Analysis, which runs
server-side against the repository rather than from CI. There is no scan step in
the workflow: SonarCloud refuses to accept both, failing the CI scan with *"You
are running CI analysis while Automatic Analysis is enabled."* See the note at
the top of `sonar-project.properties` for what that costs and how to switch back.

### Repository secrets

Set these under Settings → Secrets and variables → Actions:

| Secret | Used for |
| --- | --- |
| `AWS_ACCESS_KEY_ID` | S3 upload and CodeDeploy |
| `AWS_SECRET_ACCESS_KEY` | S3 upload and CodeDeploy |

No secret is written into the deployment bundle.

### Server configuration

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
update still runs, and says so instead of notifying.

## Dependencies

Composer runs inside the container, against the same PHP the site runs on, and
`./docker-run.sh` fails if it does not succeed.

Smarty, AltoRouter and PHPUnit are held at the oldest releases that support PHP 8
— Smarty 4 rather than 5, to avoid its template changes, since the site uses a
single template and only `assign()` and `display()`.

Note that `vendor/` is gitignored, so a fresh clone has none: run
`./docker-run.sh`, which installs dependencies as part of standing the site up.

