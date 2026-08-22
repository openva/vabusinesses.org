#!/usr/bin/env bash

# See if the remote ZIP file exists
if [ "$(curl -Is https://cis.scc.virginia.gov/DataSales/DownloadBEDataSalesFile |grep -c '200 OK')" -lt 1 ]; then
    echo "ERROR: The ZIP files do not exist on the SCC website"
    ERRORED=true
fi

# See if the update script executes cleanly
if ! ../../scripts/update.sh; then
    echo "ERROR: Update script failed"
    ERRORED=true
fi

# See if every CSV file we expect was extracted. Counting them is not enough:
# the SCC adds files over time, and a count silently passes when one expected
# file is swapped for an unexpected one.
for expected in amendment corp llc lp merger name_history officer reserved_name tables gp bt psa
do
    if [ ! -s "../../data/$expected.csv" ]; then
        echo "ERROR: ../../data/$expected.csv is missing or empty"
        ERRORED=true
    fi
done

# See if the SQLite file exists
if [[ ! -e ../../data/vabusinesses.sqlite ]]; then
    echo "ERROR: SQLite file not found"
    ERRORED=true
    
else
    # See if the tables we expect exist in SQLite. Checked one at a time rather
    # than by comparing against a single joined string, which depended on the
    # order ".tables" happened to print them in.
    for table in amendment corp llc lp merger name_history officer reserved_name tables gp bt psa
    do
        if [ "$(sqlite3 ../../data/vabusinesses.sqlite "SELECT count(*) FROM sqlite_master WHERE type='table' AND name='$table';")" -ne 1 ]; then
            echo "ERROR: SQLite table '$table' was not created"
            ERRORED=true
        fi
    done

    # See if we have a reasonable number of records in SQLite's corp table
    if [ "$(sqlite3 ../../data/vabusinesses.sqlite 'SELECT COUNT(*) FROM corp')" -lt 350000 ]; then
        echo "ERROR: Insufficient SQLite rows found for corporate data"
        ERRORED=true
    fi

    # See if we have a reasonable number of records in SQLite's llc table
    if [ "$(sqlite3 ../../data/vabusinesses.sqlite 'SELECT COUNT(*) FROM llc')" -lt 730000 ]; then
        echo "ERROR: Insufficient SQLite rows found for llc data"
        ERRORED=true
    fi

    # See if we have a reasonable number of records in SQLite's officer table
    if [ "$(sqlite3 ../../data/vabusinesses.sqlite 'SELECT COUNT(*) FROM officer')" -lt 650000 ]; then
        echo "ERROR: Insufficient SQLite rows found for officers data"
        ERRORED=true
    fi

fi

# If any tests failed, have this script return that failure
if [ "$ERRORED" == true ]; then
    exit 1
fi
