#!/usr/bin/env bash

cd $(dirname "$0")

# Retrieve bulk data
curl -s -o /tmp/data.zip http://scc.virginia.gov/clk/data/CISbemon.CSV.zip

# Uncompress the ZIP file
unzip -d ../data/ /tmp/data.zip

# Rename files to lowercase
rename 'y/A-Z/a-z/' ../data/*

# Eliminate the periods from a pair of filename
mv name.history.csv name_history.csv
mv reserved.name.csv reserved_name.csv

# Delete temporary artifacts
rm /tmp/data.zip

cd ../data/
sqlite3 vabusinesses.sqlite < ../scripts/load-data.sql
