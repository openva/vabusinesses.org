<?php

/**
 * The other businesses registered at one business's address.
 *
 * Matched on the coordinates that scripts/geocode-businesses.php writes to the
 * entity tables, not on the address text: the SCC stores one building's address
 * several ways ("814 E Main St", "814 East Main Street", "814 E. MAIN ST."), and
 * only the geocoded coordinate is the same for all of them. At 814 East Main
 * Street in Richmond that is the difference between finding 42 businesses and
 * finding all 84.
 **/
class RelatedBusinesses
{

    /*
     * Declared explicitly because PHP 8.2 deprecates dynamic properties.
     */
    public $db;
    public $id;
    public $type;
    public $businesses;

    /*
     * How many businesses to list. A few addresses -- registered agents like CT
     * Corporation, and the office buildings that host them -- are shared by
     * thousands of entities, which is a list nobody reads and a page nobody
     * wants to load. One more than this is fetched, so that the caller can tell
     * a list that fits from one that was cut short.
     */
    const LIMIT = 50;

    /**
     * Fetch the businesses sharing this business's coordinates
     *
     * @return array|false the businesses, or FALSE on error
     */
    function fetch()
    {

        if (!isset($this->db) || !isset($this->id))
        {
            return false;
        }

        /*
         * The coordinate columns arrive with the weekly rebuild, so a database
         * built before this feature existed does not have them yet. Deployed
         * code is newer than the data it is pointed at for as long as that takes,
         * and a business page is not worth failing over a section that cannot be
         * filled in yet.
         */
        if (!$this->coordinates_exist())
        {
            return array();
        }

        $point = $this->coordinates();

        /*
         * A business whose address is not in the geocode cache has no
         * coordinates, and so nothing to be related to. That is not an error:
         * about a third of them are in that position.
         */
        if ($point === false)
        {
            return array();
        }

        /*
         * One UNION ALL across the entity tables, so that the limit applies to
         * the combined list rather than to each table's share of it.
         *
         * Status is restricted the way the map restricts it: PENDINACT is a
         * business behind on its filings but still registered, while the rest of
         * the inactive records are long-dissolved businesses that would bury the
         * ones actually at the address today.
         *
         * Each table's own contribution is sorted and cut to length before the
         * six are combined. A handful of addresses -- registered agents -- have
         * thousands of businesses, and sorting all of them to keep fifty takes a
         * second. The fifty-one best of six sorted lists are necessarily among
         * the fifty-one best of each.
         */
        $selects = array();

        foreach (Business::ENTITY_TABLES as $table)
        {
            $selects[] = 'SELECT * FROM (
                          SELECT EntityID, Name, Status
                          FROM ' . $table . '
                          WHERE Latitude = :latitude
                          AND Longitude = :longitude
                          AND EntityID <> :id
                          AND Status IN ("ACTIVE", "PENDINACT")
                          ORDER BY Name COLLATE NOCASE ASC
                          LIMIT ' . (self::LIMIT + 1) . ')';
        }

        /*
         * Fetching one more than the limit is what distinguishes "these are all
         * of them" from "these are the first fifty".
         *
         * The duplicates have to be removed before that limit rather than after
         * it. The SCC ships some entities in more than one table, and at an
         * address with thousands of businesses a single duplicate among the
         * fifty-one rows fetched would leave exactly fifty after deduplication
         * -- which reads as a list that fits, and the page would quietly present
         * the first fifty of three thousand as all of them.
         *
         * GROUP BY, not DISTINCT: the copies are not always byte-identical, so
         * DISTINCT over the whole row would keep them both.
         */
        $sql = 'SELECT EntityID, Name, Status FROM ('
             . implode(' UNION ALL ', $selects)
             . ') GROUP BY EntityID'
             . ' ORDER BY Name COLLATE NOCASE ASC'
             . ' LIMIT ' . (self::LIMIT + 1);

        $statement = $this->db->prepare($sql);
        if ($statement === false)
        {
            return false;
        }

        $statement->bindValue(':id', $this->id, SQLITE3_TEXT);
        $statement->bindValue(':latitude', $point['Latitude'], SQLITE3_FLOAT);
        $statement->bindValue(':longitude', $point['Longitude'], SQLITE3_FLOAT);

        $result = $statement->execute();
        if ($result === false)
        {
            return false;
        }

        /*
         * Keyed by identifier, because the SCC ships some entities in more than
         * one table and a business should not be listed twice as its own
         * neighbour.
         */
        $this->businesses = array();

        while ($business = $result->fetchArray(SQLITE3_ASSOC))
        {
            $this->businesses[trim($business['EntityID'])] = $business;
        }

        $this->businesses = array_values($this->businesses);

        return $this->businesses;

    }

    /**
     * Whether this database has been through scripts/geocode-businesses.php.
     *
     * Asked with PRAGMA rather than by letting the query fail: SQLite3::prepare()
     * raises a PHP warning on an unknown column, and a warning is printed into
     * the response body, which is enough to make the API's JSON unparseable and
     * the page a 500. Checking first is what keeps the failure quiet.
     *
     * @return bool
     */
    private function coordinates_exist()
    {

        $result = $this->db->query('PRAGMA table_info(corp)');

        if ($result === false)
        {
            return false;
        }

        while ($column = $result->fetchArray(SQLITE3_ASSOC))
        {
            if ($column['name'] === 'Latitude')
            {
                return true;
            }
        }

        return false;

    }

    /**
     * This business's own coordinates, read from the table it is stored in.
     *
     * Deliberately read here rather than accepted from the caller, which has
     * them already: they cannot survive the trip. Business::fetch() trims every
     * column it returns, which renders the coordinate as a string at PHP's
     * default precision of 14 significant digits, and the API then serialises it
     * to JSON. 37.538593855599842 comes back as 37.5385938556 -- a different
     * float, equal to nothing in the table, and every page would show no
     * neighbours at all. Passed between two queries as a float it is exact.
     *
     * @return array|false the coordinates, or FALSE if this business has none
     */
    private function coordinates()
    {

        /*
         * $type is the table Business::fetch() found the record in. It only
         * narrows the search, so an absent or unrecognised value costs five
         * extra indexed lookups rather than a wrong answer.
         */
        $tables = in_array($this->type, Business::ENTITY_TABLES, true)
            ? array($this->type)
            : Business::ENTITY_TABLES;

        foreach ($tables as $table)
        {
            $statement = $this->db->prepare(
                'SELECT Latitude, Longitude
                FROM ' . $table . '
                WHERE EntityID = :id
                AND Latitude IS NOT NULL
                AND Longitude IS NOT NULL
                LIMIT 1'
            );

            if ($statement === false)
            {
                continue;
            }

            $statement->bindValue(':id', $this->id, SQLITE3_TEXT);

            $result = $statement->execute();

            if ($result === false)
            {
                continue;
            }

            $row = $result->fetchArray(SQLITE3_ASSOC);

            if (is_array($row))
            {
                return $row;
            }
        }

        return false;

    }

}
