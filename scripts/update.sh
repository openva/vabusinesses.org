#!/usr/bin/env bash

# Retrieve bulk data
curl -s -o /tmp/data.zip http://scc.virginia.gov/clk/data/CISbemon.CSV.zip

# Uncompress the ZIP file
unzip -d /vol/vabusinesses.org/htdocs/data/ /tmp/data.zip

# Rename files to lowercase
rename 'y/A-Z/a-z/' /vol/vabusinesses.org/htdocs/data/*

# Delete temporary artifacts
rm /tmp/data.zip
