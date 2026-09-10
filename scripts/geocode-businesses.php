<?php

/**
 * Fill in the Latitude and Longitude columns on every entity table.
 *
 * The SCC does not publish coordinates, and its address text is not normalised:
 * "814 E Main St", "814 East Main Street" and "814 E. MAIN ST." are one building
 * in Richmond, stored three ways. Comparing address strings therefore cannot
 * tell which businesses share an address, but the geocoder resolves all three to
 * one coordinate pair, so the coordinate can.
 *
 * data/addresses.db holds those coordinates, keyed by a hash of the address (see
 * includes/class.Geocode.php). Looking a business up there costs one indexed
 * read, which is cheap enough for the one address a business page draws a map
 * for and far too slow for the million-odd rows that page would have to hash to
 * find the other businesses at that address. So the lookup is done once, here,
 * and the answer is written to the entity tables.
 *
 * Run against the newly built database before it is promoted, so that a
 * half-geocoded file never becomes the live one.
 *
 * Usage: php scripts/geocode-businesses.php [path to sqlite file]
 */

$root = dirname(__DIR__);

require $root . '/includes/class.Geocode.php';

$tables = array('corp', 'llc', 'lp', 'gp', 'bt', 'psa');

$business_db = $argv[1] ?? ($root . '/data/vabusinesses.sqlite');

if (!is_writable($business_db))
{
    fwrite(STDERR, "geocode-businesses.php: cannot write to $business_db\n");
    exit(1);
}

/*
 * Geocode::connect() reads DOCUMENT_ROOT, which CLI does not set.
 */
$_SERVER['DOCUMENT_ROOT'] = $root;

$geocode = new Geocode;

if ($geocode->connect() === FALSE)
{
    fwrite(STDERR, "geocode-businesses.php: data/addresses.db not found; nothing to geocode\n");
    exit(1);
}

$db = new SQLite3($business_db);

/*
 * Rows are read and written in one pass over each table, so the write must not
 * disturb the cursor the read is using. Reading every row into memory first
 * would be a gigabyte; updating by rowid from a second connection would deadlock
 * on the write lock this one holds. Collecting a table's updates and applying
 * them after its SELECT has been exhausted avoids both.
 */
$located = $missed = 0;

foreach ($tables as $table)
{
    /*
     * General partnerships, business trusts and public service authorities
     * postdate the site, and update.sh tolerates the SCC shipping an archive
     * without them. A database built from such an archive has no such table,
     * which is not a failure -- and this runs before the new database is
     * promoted, so treating it as one would discard an otherwise good update.
     */
    if ($db->querySingle(
        'SELECT 1 FROM sqlite_master WHERE type = "table" AND name = "' . $table . '"'
    ) === NULL)
    {
        echo '  ' . $table . ": not in this database, skipping\n";
        continue;
    }

    /*
     * An address with no street or no city cannot have been geocoded, so there
     * is nothing to look up for those rows.
     */
    $result = $db->query(
        'SELECT rowid, Street1, Street2, City, State, Zip
        FROM ' . $table . '
        WHERE Street1 <> ""
        AND City <> ""'
    );

    if ($result === FALSE)
    {
        fwrite(STDERR, "geocode-businesses.php: cannot read $table\n");
        exit(1);
    }

    $updates = array();

    while ($row = $result->fetchArray(SQLITE3_ASSOC))
    {
        $point = $geocode->coordinates(
            $row['Street1'],
            $row['Street2'],
            $row['City'],
            $row['State'],
            $row['Zip']
        );

        if ($point === FALSE)
        {
            /*
             * Not every address is in the geocode cache. Those rows keep NULL
             * coordinates and simply do not appear at any address.
             */
            $missed++;
            continue;
        }

        $updates[] = array($row['rowid'], $point['latitude'], $point['longitude']);
    }

    $result->finalize();

    $statement = $db->prepare(
        'UPDATE ' . $table . '
        SET Latitude = :latitude, Longitude = :longitude
        WHERE rowid = :rowid'
    );

    if ($statement === FALSE)
    {
        fwrite(STDERR, "geocode-businesses.php: cannot prepare update for $table\n");
        exit(1);
    }

    /*
     * One transaction per table. Committing per row would fsync a million times
     * and take hours.
     */
    $db->exec('BEGIN');

    foreach ($updates as $update)
    {
        $statement->bindValue(':rowid', $update[0], SQLITE3_INTEGER);
        $statement->bindValue(':latitude', $update[1], SQLITE3_FLOAT);
        $statement->bindValue(':longitude', $update[2], SQLITE3_FLOAT);

        if ($statement->execute() === FALSE)
        {
            $db->exec('ROLLBACK');
            fwrite(STDERR, "geocode-businesses.php: cannot update $table\n");
            exit(1);
        }

        $statement->reset();
    }

    $db->exec('COMMIT');

    $located += count($updates);

    echo '  ' . $table . ': ' . number_format(count($updates)) . " geocoded\n";
}

printf(
    "%s businesses geocoded, %s not in the geocode cache\n",
    number_format($located),
    number_format($missed)
);
