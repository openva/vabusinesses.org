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

# See if the right number of CSV files exist
if [ "$(ls ../../data/*.csv |wc -l)" -ne 8 ]; then
    echo "ERROR: Improper number of CSV files were extracted"
    ERRORED=true
fi

# See if the SQLite file exists
if [[ ! -e ../../data/vabusinesses.sqlite ]]; then
    echo "ERROR: SQLite file not found"
    ERRORED=true
    
else
    # See if the right tables exist in SQLite
    if [ "$(sqlite3 ../../data/vabusinesses.sqlite .tables |perl -pne 's/\s+/,/g')" != "amendment,llc,merger,officer,tables,corp,lp,name_history,reserved_name," ]; then
        echo "ERROR: Unexpected list of SQLite tables created"
        ERRORED=true
    fi

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
