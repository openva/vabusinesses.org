<?php

/**
 * Strip padding from the edges of every field in a CSV.
 *
 * Current SCC exports pad fields with a leading tab and trailing spaces (an
 * entity ID arrives as "\t11380768  ", a status as "INACTIVE  "). Stored
 * verbatim, that padding breaks every lookup by ID.
 *
 * This is CSV-aware on purpose: a naive s/[[:blank:]]*,[[:blank:]]*\/,\/ also
 * rewrites the comma inside quoted values, turning "AMERICAN BRANDS, INC."
 * into "AMERICAN BRANDS,INC.". Parsing the fields leaves those alone.
 *
 * Usage: php trim-csv.php < in.csv > out.csv
 */

$in = fopen('php://stdin', 'r');
$out = fopen('php://stdout', 'w');

if ($in === FALSE || $out === FALSE)
{
    fwrite(STDERR, "trim-csv.php: could not open streams\n");
    exit(1);
}

while (($fields = fgetcsv($in, 0, ',', '"', '')) !== FALSE)
{
    /*
     * A blank line parses as a single NULL field; keep it a blank line rather
     * than emitting a spurious empty value.
     */
    if (count($fields) === 1 && $fields[0] === NULL)
    {
        fwrite($out, "\n");
        continue;
    }

    foreach ($fields as &$field)
    {
        $field = trim((string) $field);
    }
    unset($field);

    fputcsv($out, $fields, ',', '"', '', "\n");
}

fclose($in);
fclose($out);
