#!/usr/bin/env bash

# Stop running if anything at all fails
set -e

function finish {
    STATUS=$?

    # Never leave a session cookie behind on disk.
    if [ -n "${COOKIE_JAR:-}" ]; then
        rm -f "$COOKIE_JAR"
    fi

    # Any non-zero exit that didn't set its own message is still a failure, and
    # must be reported as one rather than silently reporting success.
    if [ "$STATUS" -ne 0 ] && [ -z "${MESSAGE:-}" ]; then
        MESSAGE="Failed: update.sh exited with status $STATUS"
    fi

    echo "$MESSAGE"

    # Use --arg so the message is actually interpolated (and JSON-escaped); the
    # previous single-quoted --data posted the literal string "$MESSAGE".
    if [ -n "${SLACK_WEBHOOK_URL:-}" ]; then
        if command -v jq > /dev/null; then
            PAYLOAD=$(jq -nc --arg text "$MESSAGE" '{text: $text}')
        else
            PAYLOAD="{\"text\":\"${MESSAGE//\"/\\\"}\"}"
        fi
        curl -s -X POST -H 'Content-type: application/json' --data "$PAYLOAD" "$SLACK_WEBHOOK_URL" > /dev/null
    fi

    exit "$STATUS"
}
trap finish EXIT


cd "$(dirname "$0")" || exit 1

CONSENT_URL="https://cis.scc.virginia.gov/Cookie/StoreCookieConsent"
DOWNLOAD_URL="https://cis.scc.virginia.gov/DataSales/DownloadBEDataSalesFile"

# Make variables of secrets available here
source ./secrets.sh

echo "Downloading data from SCC"

# The SCC now gates downloads behind a cookie-consent interstitial: requesting
# the file without consent returns a 302 to /Cookie/CookieConsent instead of the
# ZIP. On that page, "Accept" POSTs to /Cookie/StoreCookieConsent, which sets a
# "cookiesAccepted" cookie. So: record consent, keep the cookie, then download.
COOKIE_JAR=$(mktemp "${TMPDIR:-/tmp}/vabusinesses-cookies.XXXXXX")

# --data '' matters: a POST with no body and no Content-Length is rejected with
# "411 Length Required", which leaves the cookie unset and the download serving
# the interstitial instead of the ZIP.
if ! curl -sS -f -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
        -X POST -H 'X-Requested-With: XMLHttpRequest' --data '' \
        --max-time 60 \
        -o /dev/null \
        "$CONSENT_URL"; then
    MESSAGE="Failed: could not record cookie consent at $CONSENT_URL"
    exit 1
fi

# -f so HTTP errors fail loudly, -L to follow any redirect. Without -f, an error
# page is happily written to the output file and curl still exits 0 -- which is
# how this failure went unnoticed: the HTML interstitial was saved as data.zip
# and the script only fell over later, at unzip.
if ! curl -sS -f -L -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
        --max-time 1800 \
        -o /tmp/data.zip \
        "$DOWNLOAD_URL"; then
    MESSAGE="Failed: $DOWNLOAD_URL could not be downloaded"
    exit 1
fi

rm -f "$COOKIE_JAR"

# Verify we actually got a ZIP, not an interstitial or error page dressed up as
# one. This is the check whose absence masked the original breakage.
if ! unzip -tqq /tmp/data.zip > /dev/null 2>&1; then
    MESSAGE="Failed: $DOWNLOAD_URL did not return a valid ZIP file (got $(file -b /tmp/data.zip 2>/dev/null || echo 'unknown content'))"
    exit 1
fi

echo "Data downloaded ($(du -h /tmp/data.zip | cut -f1))"

# Uncompress the ZIP file
if ! unzip -q -o -d /tmp/data/ /tmp/data.zip; then
    MESSAGE="CISbemon.CSV.zip could not be unzipped"
    exit 1
fi
echo "Data files unzipped"

# Delete temporary artifacts
rm /tmp/data.zip

echo Deleted stuff

# Rename files to be lowercase, some to not have a period. If the SCC renames or
# drops a file, say so plainly instead of carrying on with a partial dataset.
declare -a renames=(
    "Amendment.csv:amendment.csv"
    "Corp.csv:corp.csv"
    "LLC.csv:llc.csv"
    "LP.csv:lp.csv"
    "Merger.csv:merger.csv"
    "Officer.csv:officer.csv"
    "NameHistory.csv:name_history.csv"
    "ReservedName.csv:reserved_name.csv"
)

# Newer entity types: general partnerships, business trusts and public service
# authorities. They carry the same schema as LP.csv. These are listed separately
# because they are tolerated if absent, unlike the files above.
declare -a optional_renames=(
    "GP.csv:gp.csv"
    "BT.csv:bt.csv"
    "PSA.csv:psa.csv"
)

for rename in "${renames[@]}"
do
    source_file="${rename%%:*}"
    target_file="${rename##*:}"
    if [ ! -f "/tmp/data/$source_file" ]; then
        MESSAGE="Failed: expected $source_file in the SCC archive, but it is not there. Contents: $(cd /tmp/data && echo *)"
        exit 1
    fi
    mv -f "/tmp/data/$source_file" "/tmp/data/$target_file"
done

for rename in "${optional_renames[@]}"
do
    source_file="${rename%%:*}"
    target_file="${rename##*:}"
    if [ -f "/tmp/data/$source_file" ]; then
        mv -f "/tmp/data/$source_file" "/tmp/data/$target_file"
    else
        echo "Note: $source_file is not in this archive, skipping it"
    fi
done

echo Renamed files

# Remove any old CSV files
if [ -d ../data/ ]; then
    rm -f ../data/*.csv
else
    mkdir ../data/
fi

echo removed old CSV files maybe

cd ../data/ || exit 1

# Move over our new CSV files
mv -f /tmp/data/*.csv .

echo Moved files

# The SCC archive no longer includes Tables.csv, but load-data.sql imports it and
# the three code-description subqueries in Business::fetch() read from it. The
# same 419 rows are checked in as includes/tables.json, so regenerate the CSV
# from there rather than shipping a database with an empty "tables" table.
if ! jq -r '(["TableID","TableContents","ColumnID","ColumnValue","Description"]),
            (.[] | [.TableID, .TableContents, .ColumnID, .ColumnValue, .Description])
            | @csv' ../includes/tables.json > tables.csv; then
    MESSAGE="Failed: could not build tables.csv from includes/tables.json"
    exit 1
fi

echo "Built tables.csv ($(($(wc -l < tables.csv) - 1)) rows)"

# These files require repair of invalid encodings
declare -a files_to_fix=("amendment.csv" "corp.csv" "llc.csv" "lp.csv" "officer.csv")

echo Listed files

# Iterate through files with encoding problems and replace SCC-originated bad
# encodings with the proper characters
for filename in "${files_to_fix[@]}"
do
    awk '{
        for (i=3; i<=NF; i++) {
            gsub(/\xa6/, " ", $i)
            gsub(/\xc0/, " ", $i)
            gsub(/\xba/, "|", $i)
            gsub(/\xa9/, "É", $i)
            gsub(/\x8b/, "Ñ", $i)
            gsub(/\x9b/, "P", $i)
            gsub(/\xec/, "O", $i)
            gsub(/\x8d/, "(", $i)
            gsub(/\xd9/, ")", $i)
            gsub(/\x88/, "É", $i)
            gsub(/\xba/, "Ö", $i)
            gsub(/\xbe/, "Ö", $i)
            gsub(/\x9c/, "Ë", $i)
            gsub(/\x8d/, "P", $i)
            gsub(/\x8e/, "Ö", $i)
            gsub(/\x90/, "Á", $i)
            gsub(/\xa5/, "Í", $i)
            gsub(/\x90/, "Á", $i)
            gsub(/\xa3/, "Ú", $i)
            gsub(/\xac/, "Ñ", $i)
        }
        print
    }' "$filename" > temp.csv
    rm -f "$filename"

    # Remove any remaining high-ASCII characters
    LANG=C tr -d '[\200-\377]' < temp.csv > "$filename"
    rm temp.csv
done

echo Replaced a bunch of stuff

# Current SCC exports pad fields with a leading tab and trailing spaces (e.g.
# "\t11380768  " for an entity ID, "INACTIVE  " for a status), which would
# otherwise be stored verbatim and break every lookup by ID.
for filename in *.csv
do
    if ! php ../scripts/trim-csv.php < "$filename" > trimmed.tmp; then
        MESSAGE="Failed: could not trim field padding from $filename"
        exit 1
    fi
    mv -f trimmed.tmp "$filename"
done

echo Trimmed field padding

# These files all have DOS carriage returns and an extra trailing comma in the
# contents, so fix both of those things
tr -d '\r' < amendment.csv |awk '{gsub(/,$/,""); print}' > temp.csv && mv -f temp.csv amendment.csv
tr -d '\r' < corp.csv |awk '{gsub(/,$/,""); print}' > temp.csv && mv -f temp.csv corp.csv
tr -d '\r' < llc.csv |awk '{gsub(/,$/,""); print}' > temp.csv && mv -f temp.csv llc.csv
tr -d '\r' < lp.csv |awk '{gsub(/,$/,""); print}' > temp.csv && mv -f temp.csv lp.csv

echo Fixed newlines

# Create a temporary SQLite file, to avoid touching any that might already
# exist (this prevents downtime). Pipe stderr to /dev/null, which is bad
# because it keeps us from knowing about errors, but for the best because
# otherwise it complains about any record that ends with a series of empty
# fields, which is hundreds of thousands.
rm -f temp.sqlite
if ! sqlite3 temp.sqlite < ../scripts/load-data.sql 2>/dev/null; then
    MESSAGE="Failed: data could not be loaded into SQLite"
    exit 1
fi

echo "Data loaded into SQLite"

# Confirm the new database actually holds records before it replaces the live
# one. Without this, a partial or empty build gets promoted to production and
# the site silently serves nothing.
for table in corp llc lp tables
do
    ROWS=$(sqlite3 temp.sqlite "SELECT count(*) FROM $table;" 2>/dev/null || echo 0)
    if [ "$ROWS" -lt 1 ]; then
        MESSAGE="Failed: table '$table' is empty in the newly built database; keeping the existing one"
        exit 1
    fi
    echo "  $table: $ROWS rows"
done

# Put the file in its final location
mv -f temp.sqlite vabusinesses.sqlite

# Log the fact that this update was made
MESSAGE="All records updated."
