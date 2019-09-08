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

# Rename files to be lowercase, some to not have a period
mv Amendment.csv amendment.csv
mv Corp.csv corp.csv
mv LLC.csv llc.csv
mv LP.csv lp.csv
mv Merger.csv merger.csv
mv Officer.csv officer.csv
mv Tables.csv tables.csv
mv Name.History.csv name_history.csv
mv Reserved.Name.csv reserved_name.csv

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
