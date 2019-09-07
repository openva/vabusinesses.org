#!/usr/bin/env bash

cd $(dirname "$0")

# Retrieve bulk data
if ! curl -s -o /tmp/data.zip http://scc.virginia.gov/clk/data/CISbemon.CSV.zip; then
    echo "Failed: http://scc.virginia.gov/clk/data/CISbemon.CSV.zip could not be downloaded"
    exit 1
fi

# Uncompress the ZIP file
if ! unzip -o -d ../data/ /tmp/data.zip; then
    echo "CISbemon.CSV.zip could not be unzipped"
    exit 1
fi

# Rename files to lowercase
rename 'y/A-Z/a-z/' ../data/*

# Eliminate the periods from a pair of filename
mv name.history.csv name_history.csv
mv reserved.name.csv reserved_name.csv

# Delete temporary artifacts
rm /tmp/data.zip

cd ../data/

# If there's an existing SQLite file, delete it, because otherwise we'd be
# appending to it
if [[ -e vabusinesses.sqlite ]]; then
    rm -f vabusinesses.sqlite
fi

if ! sqlite3 vabusinesses.sqlite < ../scripts/load-data.sql; then
    echo "Error: CSV files could not be loaded into SQLite"
    exit 1
fi
