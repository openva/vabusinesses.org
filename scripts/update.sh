#!/usr/bin/env bash

# Stop running if anything at all fails
set -e

cd $(dirname "$0") || exit 1

echo "Downloading data from SCC"

# Retrieve bulk data
if ! curl -s -o /tmp/data.zip http://scc.virginia.gov/clk/data/CISbemon.CSV.zip; then
    echo "Failed: http://scc.virginia.gov/clk/data/CISbemon.CSV.zip could not be downloaded"
    exit 1
fi

echo "Data downloaded"

# Uncompress the ZIP file
if ! unzip -q -o -d ../data/ /tmp/data.zip; then
    echo "CISbemon.CSV.zip could not be unzipped"
    exit 1
fi

# Delete temporary artifacts
rm /tmp/data.zip

cd ../data/ || exit 1

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

# These files require repair of invalid encodings
declare -a files_to_fix=("amendment.csv" "corp.csv" "llc.csv" "lp.csv" "officer.csv")

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

# These files all have DOS carriage returns and an extra trailing comma in the
# contents, so fix both of those things
tr -d '\r' < amendment.csv |awk '{gsub(/,$/,""); print}' > temp.csv && mv -f temp.csv amendment.csv
tr -d '\r' < corp.csv |awk '{gsub(/,$/,""); print}' > temp.csv && mv -f temp.csv corp.csv
tr -d '\r' < llc.csv |awk '{gsub(/,$/,""); print}' > temp.csv && mv -f temp.csv llc.csv
tr -d '\r' < lp.csv |awk '{gsub(/,$/,""); print}' > temp.csv && mv -f temp.csv lp.csv

# Create a temporary SQLite file, to avoid touching any that might already
# exist (this prevents downtime). Pipe stderr to /dev/null, which is bad
# because it keeps us from knowing about errors, but for the best because
# otherwise it complains about any record that ends with a series of empty
# fields, which is hundreds of thousands.
sqlite3 temp.sqlite < ../scripts/load-data.sql 2>/dev/null
echo "Data loaded into SQLite"

# Put the file in its final location
mv -f temp.sqlite vabusinesses.sqlite
