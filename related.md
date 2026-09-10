# Plan: "Related businesses" (same coordinates) on the business detail page

## Goal

On a business's detail page (`business.php`), show a list of other businesses located
at the same physical address ("related" = "same coordinates").

## Background / investigation findings

This is a flat PHP 8.2 app (no framework/ORM, no Rails-style conventions):

- Business records live in `data/vabusinesses.sqlite`, one table per entity type:
  `corp`, `llc`, `lp`, `gp`, `bt`, `psa` (see `Business::ENTITY_TABLES` in
  `includes/class.Business.php`). Columns include `EntityID`, `Name`, `Status`,
  `Street1`, `Street2`, `City`, `State`, `Zip`, etc. This database is fully rebuilt
  from SCC-published CSVs every week (see `scripts/update.sh` and
  `scripts/load-data.sql`) and atomically swapped into place.
- **There is no latitude/longitude column on any business table.** Coordinates are
  resolved at request time by hashing the business's address and looking it up in a
  *separate* SQLite database, `data/addresses.db`, via `includes/class.Geocode.php`.
  - `addresses.db` schema: `addresses(address_hash TEXT PRIMARY KEY, address_cleaned
    TEXT, latitude, longitude, date, source)`. ~637,000 rows, ~99MB.
  - The hash key is `Geocode::hash($street1, $street2, $city, $state, $zip)` — an MD5
    of the uppercased, whitespace-collapsed address parts (see doc comment at the top
    of `includes/class.Geocode.php` for the exact recipe; it's a compatibility
    contract with the external geocoder, "the `crump` project", so must not change).
  - `Geocode::coordinates(...)` computes the hash and does a single indexed lookup by
    primary key. It's called from `business.php` only when rendering a page (for the
    map), and from `scripts/build-map-data.php` (an offline job that builds
    `data/map/*.json`, used by the sitewide business map).
- **Important finding: address strings in the entity tables are NOT reliably
  normalized.** Verified example: the same Richmond building at 814 E Main St has at
  least 9 distinct string variants across real rows ("814 E Main St", "814 East Main
  Street", "814 E. Main St.", different casing, different ZIP+4 presence, etc.). Two
  of these variants were confirmed to produce **different** `Geocode::hash()` values
  but resolve to the **identical** lat/lng in `addresses.db` (the external geocoder
  does real address normalization that `Geocode::hash()` does not attempt).
  - **Conclusion: matching related businesses by address string or by
    `Geocode::hash()` equality is unreliable and will miss real matches.** The only
    reliable join key is the resolved geocoded coordinate (lat/lng).
- Computing `Geocode::hash()` / looking up coordinates live for every business row
  (~954,000 active rows across all entity tables — see counts below) on every page
  view is too expensive to do live. The codebase's own comments elsewhere
  independently make this point (e.g. `scripts/load-data.sql`: "Every business page
  is a lookup by EntityID. Without these [indexes], each one is a full table scan of
  up to 1.5 million rows.").
  - Active/`PENDINACT` row counts with a non-empty address, by table: corp 234,053;
    llc 711,413; lp 5,904; gp 1,633; bt 895; psa 32. Total ≈ 954,000.
- Decision made with the user: **denormalize `Latitude`/`Longitude` onto each entity
  table**, computed once during the offline data-load pipeline (not live). This
  avoids ever touching `addresses.db` at request time and reduces the related-lookup
  query to a simple indexed equality query per entity table.
- The existing "Location" section of `business.php` (~lines 200–247) already does the
  live `Geocode::coordinates()` lookup for the on-page map and is the natural
  insertion point for a new "Related businesses" section right after it.
- `includes/class.Officers.php` is the existing precedent/template for "a small class
  that fetches related-by-EntityID records and gets rendered as its own section" —
  new code should follow its shape (`db`, `id` properties, a `fetch()` method).
- `api/business.php` (the internal JSON API that `business.php` itself calls — see
  `includes/header.php` for why: "the site is a consumer of the same API third
  parties use") already merges in `Officers::fetch()` results under an `Officers` key
  (lines ~62–65). A `RelatedBusinesses` key should be added the same way, to keep the
  page and the API in sync.
- Test framework: PHPUnit 9. Tests live in `deploy/tests/*Test.php` (see
  `deploy/tests/BusinessTest.php` for the convention: `require_once` autoload +
  `includes/header.php`, then a plain `PHPUnit\Framework\TestCase` subclass).
  `deploy/tests/phpunit.xml` restricts coverage to `includes/`.
- No caching layer exists anywhere in the app (no memcached/Redis/APCu). The
  established pattern for expensive lookups is precomputing offline and serving
  static files/cheap indexed queries — not a request-time cache.
- `scripts/update.sh` is the weekly cron-driven pipeline (see `deploy/crontab`):
  downloads SCC data → cleans/repairs CSVs → loads into a `temp.sqlite` via
  `scripts/load-data.sql` → validates row counts → `mv`s it into place as
  `vabusinesses.sqlite` → then (non-fatally) runs `scripts/build-map-data.php` to
  regenerate `data/map/*.json` from the *new* database plus `addresses.db`.

## Plan

### 1. Schema: add `Latitude`/`Longitude` columns to entity tables

In `scripts/load-data.sql`, after the CSV `.import` statements and alongside the
existing `CREATE INDEX` statements, add for each of the six entity tables
(`corp`, `llc`, `lp`, `gp`, `bt`, `psa`):

```sql
ALTER TABLE corp ADD COLUMN Latitude REAL;
ALTER TABLE corp ADD COLUMN Longitude REAL;
CREATE INDEX corpLatLng ON corp (Latitude, Longitude);
-- repeat for llc, lp, gp, bt, psa
```

(Columns start NULL for every row; they're backfilled in step 2.)

### 2. New offline backfill script: `scripts/geocode-businesses.php`

- Modeled closely on `scripts/build-map-data.php` (same `Geocode` usage, same
  `ENTITY_TABLES` loop, same non-fatal-if-`addresses.db`-missing posture).
- Opens the **just-built** `temp.sqlite` read-write (not the live
  `vabusinesses.sqlite`) and `data/addresses.db` read-only via `Geocode::connect()`.
- For each entity table, for each row with a non-empty `Street1`/`City`:
  compute `Geocode::coordinates($street1, $street2, $city, $state, $zip)` and, if
  found, `UPDATE <table> SET Latitude = :lat, Longitude = :lng WHERE EntityID = :id`.
  Rows that don't geocode are left with `NULL` lat/lng (they simply won't participate
  in related-business matching — same graceful-degradation posture as the existing
  map).
- Batch updates inside a transaction (`BEGIN`/`COMMIT`) for performance across
  ~954,000 rows.
- Wire into `scripts/update.sh`: run this script **after** `temp.sqlite` is built and
  validated (after the existing row-count checks, ~line 283) but **before**
  `mv -f temp.sqlite vabusinesses.sqlite` (~line 298) — i.e., insert it as a new step
  that modifies `temp.sqlite` in place, prior to promotion.
  - **Open question / needs a decision when implementing:** should a failure in this
    backfill step **abort the promotion** (treat it like the existing row-count
    validation — don't ship a database with no coordinates) or be **non-fatal**
    (like `build-map-data.php` today, which runs *after* promotion and only logs a
    note on failure)? Since this script would run *before* promotion and mutates the
    file about to become live, failing loudly and aborting (`exit 1`, matching the
    pattern used for the row-count checks) is probably right — but confirm before
    wiring it in, since `update.sh` uses `set -e` and a consistent failure posture
    matters for the Slack-notification/trap logic at the top of that script.

### 3. New class: `includes/class.RelatedBusinesses.php`

Follow the shape of `includes/class.Officers.php` exactly (public `$db`, `$id`
properties declared explicitly for PHP 8.2's dynamic-property deprecation, a
`fetch()` method returning an array or `false`):

- Given `$this->id` (the current business's EntityID), first determine which entity
  table it lives in and its own `Latitude`/`Longitude` (the caller — `api/business.php`
  — already knows this from `Business::fetch()`/`Business::$type`, so consider passing
  lat/lng in directly rather than re-deriving them, to avoid a second lookup).
- If lat/lng is `NULL` (ungeocoded), return an empty result — nothing to relate.
- Query **each** of the six entity tables for rows where `Latitude = :lat AND
  Longitude = :lng`, excluding the current EntityID, restricted to
  `Status IN ('ACTIVE', 'PENDINACT')` (same convention as
  `scripts/build-map-data.php`'s `$statuses` constant — "not expired").
- Union/collect results across all six tables, dedupe by EntityID (an entity can
  legitimately appear in more than one table historically — see comments in
  `class.Business.php` and `build-map-data.php` about this), and cap the result count
  (e.g. `LIMIT 50`) to guard against addresses shared by registered-agent mills (CT
  Corporation, CSC, etc.) hosting thousands of entities — this exact concern is
  already called out in `build-map-data.php`'s comments about marker stacking.
- Return enough per-row data to render a name + link (`EntityID`, `Name` at minimum).

### 4. Wire into `business.php`

- Insert a new "Related businesses" section immediately after the existing Location
  section (~line 247 in the current file), following the same
  `detail_section()`-style rendering used elsewhere on the page.
- Render each match as a link to `/business/{EntityID}` with the business name.
- Omit the section entirely if there are zero matches (consistent with how other
  sections on this page behave when empty — see `detail_section()`'s
  empty-rows-returns-empty-string behavior).
- If the result was capped at the limit, indicate the list isn't exhaustive (e.g.
  "and N more businesses at this address") rather than implying completeness.

### 5. Extend `api/business.php`

- Mirror how `Officers::fetch()` is merged into the response today (~lines 62–65):
  instantiate `RelatedBusinesses`, set `->db`/`->id` (and lat/lng, per however step 3
  is finalized), call `->fetch()`, and add the result under a `RelatedBusinesses` key
  in the JSON response — keeping the server-rendered page and the public API in sync,
  per this project's stated philosophy that the page is just another API consumer.

### 6. Tests

- Add `deploy/tests/RelatedBusinessesTest.php` alongside `BusinessTest.php`, following
  the same convention (`require_once` autoload + `includes/header.php`, plain
  `PHPUnit\Framework\TestCase` subclass).
- Cases to cover: two+ businesses sharing exact lat/lng are returned for each other;
  businesses at different coordinates are not returned; the business itself is
  excluded from its own related list; inactive/expired-status businesses at the same
  address are excluded; the cap/limit behavior when many businesses share one
  address; a business with `NULL` lat/lng (ungeocoded) returns an empty list rather
  than erroring.

### 7. Deployment/ops follow-up

- Confirm the new `scripts/geocode-businesses.php` step actually gets added to
  `scripts/update.sh` (not just written) and runs in the right place relative to the
  existing steps, per step 2 above.
- No changes needed to `deploy/crontab` itself — it just invokes `update.sh`, which is
  where the new step lives.

## Explicit decisions already made (do not re-litigate without cause)

- Matching is by **exact** denormalized `Latitude`/`Longitude` equality on entity
  table rows — not by rounded/binned coordinates (that rounding, used in
  `build-map-data.php`, is a *display* concern for avoiding stacked map markers, not
  an identity concern for "is this the same address").
- Matching is **not** done via address string equality or `Geocode::hash()` equality
  — both were proven unreliable (see investigation findings above).
- The backfill is **offline/batch**, run as part of the existing weekly data-refresh
  pipeline — not a live per-request computation.
