#!/usr/bin/env bash

# Stop running if anything at all fails
set -e

function finish {
    echo "$MESSAGE"
    if [ ! -z ${SLACK_WEBHOOK_URL+x} ]; then
        curl -s -X POST -H 'Content-type: application/json' --data '{"text":$MESSAGE}' "$SLACK_WEBHOOK_URL"
    fi
}
trap finish EXIT


cd "$(dirname "$0")" || exit 1

# Make variables of secrets available here
source ./secrets.sh

echo "Downloading data from SCC"

# Retrieve bulk data
if ! curl -s -o /tmp/data.zip https://cis.scc.virginia.gov/DataSales/DownloadBEDataSalesFile; then
    MESSAGE="Failed: https://cis.scc.virginia.gov/DataSales/DownloadBEDataSalesFile could not be downloaded"
    exit 1
fi

echo "Data downloaded"

# Uncompress the ZIP file
if ! unzip -q -o -d /tmp/data/ /tmp/data.zip; then
    MESSAGE="CISbemon.CSV.zip could not be unzipped"
    exit 1
fi
echo "Data files unzipped"

# Delete temporary artifacts
rm /tmp/data.zip

echo Deleted stuff

# Rename files to be lowercase, some to not have a period
mv -f /tmp/data/Amendment.csv /tmp/data/amendment.csv
mv -f /tmp/data/Corp.csv /tmp/data/corp.csv
mv -f /tmp/data/LLC.csv /tmp/data/llc.csv
mv -f /tmp/data/LP.csv /tmp/data/lp.csv
mv -f /tmp/data/Merger.csv /tmp/data/merger.csv
mv -f /tmp/data/Officer.csv /tmp/data/officer.csv
mv -f /tmp/data/NameHistory.csv /tmp/data/name_history.csv
mv -f /tmp/data/ReservedName.csv /tmp/data/reserved_name.csv

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
sqlite3 temp.sqlite < ../scripts/load-data.sql 2>/dev/null

if [ $? -eq 0 ]; then
    echo "Data loaded into SQLite"
else
    echo "Data could not be loaded into SQLite"
fi

# Put the file in its final location
mv -f temp.sqlite vabusinesses.sqlite

# Log the fact that this update was made
MESSAGE="All records updated."
