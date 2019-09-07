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

# Create a temporary SQLite file, to avoid touching any that might already
# exist (this prevents downtime)
if ! sqlite3 temp.sqlite < ../scripts/load-data.sql; then
    echo "Error: CSV files could not be loaded into SQLite"
    exit 1
fi

# Put the file in its final location
mv -f temp.sqlite vabusinesses.sql
