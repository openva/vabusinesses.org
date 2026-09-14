#!/usr/bin/env bash

# update.sh geocodes the newly built database against data/addresses.db and
# treats a missing cache as fatal -- correctly, in production, since promoting a
# database with no coordinates would silently break "related businesses" on
# every page. That cache is ~100 MB, gitignored, and only ever fetched from S3
# during deploy (see deploy/postdeploy.sh), so it never exists here. Stand up an
# empty one with the schema Geocode::connect()/coordinates() expect (see
# includes/class.Geocode.php) so the real geocoding code path runs in CI; every
# lookup simply misses, the same as any address the real cache doesn't cover.
if [[ ! -e ../../data/addresses.db ]]; then
    mkdir -p ../../data
    sqlite3 ../../data/addresses.db \
        'CREATE TABLE addresses (address_hash TEXT PRIMARY KEY, latitude REAL, longitude REAL);'
fi

# See if the remote ZIP file is available. The SCC gates downloads behind a
# cookie-consent interstitial, so an unauthenticated request is answered with a
# 302 to /Cookie/CookieConsent rather than the file -- record consent first, the
# same way scripts/update.sh does, or this always reports the file as missing.
COOKIE_JAR=$(mktemp "${TMPDIR:-/tmp}/vabusinesses-test-cookies.XXXXXX")
trap 'rm -f "$COOKIE_JAR"' EXIT

curl -sS -f -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
    -X POST -H 'X-Requested-With: XMLHttpRequest' --data '' \
    --max-time 60 -o /dev/null \
    "https://cis.scc.virginia.gov/Cookie/StoreCookieConsent" || true

# Ask for a single byte: enough to prove the file is served, without pulling
# down 169 MB just to check.
ZIP_TYPE=$(curl -sS -f -L -b "$COOKIE_JAR" -c "$COOKIE_JAR" -r 0-0 \
    --max-time 60 -o /dev/null -w '%{content_type}' \
    "https://cis.scc.virginia.gov/DataSales/DownloadBEDataSalesFile" || echo "none")

case "$ZIP_TYPE" in
    *zip*|*octet-stream*)
        ;;
    *)
        echo "ERROR: the SCC did not serve the data file (content type: $ZIP_TYPE)" >&2
        ERRORED=true
        ;;
esac

# See if the update script executes cleanly
if ! ../../scripts/update.sh; then
    echo "ERROR: Update script failed" >&2
    ERRORED=true
fi

# See if every CSV file we expect was extracted. Counting them is not enough:
# the SCC adds files over time, and a count silently passes when one expected
# file is swapped for an unexpected one.
for expected in amendment corp llc lp merger name_history officer reserved_name tables gp bt psa
do
    if [[ ! -s "../../data/$expected.csv" ]]; then
        echo "ERROR: ../../data/$expected.csv is missing or empty" >&2
        ERRORED=true
    fi
done

# See if the SQLite file exists
if [[ ! -e ../../data/vabusinesses.sqlite ]]; then
    echo "ERROR: SQLite file not found" >&2
    ERRORED=true
    
else
    # See if the tables we expect exist in SQLite. Checked one at a time rather
    # than by comparing against a single joined string, which depended on the
    # order ".tables" happened to print them in.
    for table in amendment corp llc lp merger name_history officer reserved_name tables gp bt psa
    do
        if [[ "$(sqlite3 ../../data/vabusinesses.sqlite "SELECT count(*) FROM sqlite_master WHERE type='table' AND name='$table';")" -ne 1 ]]; then
            echo "ERROR: SQLite table '$table' was not created" >&2
            ERRORED=true
        fi
    done

    # See if we have a reasonable number of records in SQLite's corp table
    if [[ "$(sqlite3 ../../data/vabusinesses.sqlite 'SELECT COUNT(*) FROM corp')" -lt 350000 ]]; then
        echo "ERROR: Insufficient SQLite rows found for corporate data" >&2
        ERRORED=true
    fi

    # See if we have a reasonable number of records in SQLite's llc table
    if [[ "$(sqlite3 ../../data/vabusinesses.sqlite 'SELECT COUNT(*) FROM llc')" -lt 730000 ]]; then
        echo "ERROR: Insufficient SQLite rows found for llc data" >&2
        ERRORED=true
    fi

    # See if we have a reasonable number of records in SQLite's officer table
    if [[ "$(sqlite3 ../../data/vabusinesses.sqlite 'SELECT COUNT(*) FROM officer')" -lt 650000 ]]; then
        echo "ERROR: Insufficient SQLite rows found for officers data" >&2
        ERRORED=true
    fi

fi

# If any tests failed, have this script return that failure
if [[ "$ERRORED" == true ]]; then
    exit 1
fi
