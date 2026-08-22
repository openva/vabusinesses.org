<?php

/**
 * Interact with business-related data
 **/
class Business
{

    /*
     * Declared explicitly because PHP 8.2 deprecates dynamic properties.
     */
    public $db;
    public $id;
    public $query;
    public $type;
    public $business;
    public $results;
    public $lookup_table;

    /*
     * The only tables holding entity records, and so the only values that may
     * ever be interpolated into a query as a table name. SQLite cannot bind a
     * table name as a parameter, so this allowlist is what keeps $type from
     * becoming an injection vector.
     */
    const ENTITY_TABLES = array('corp', 'llc', 'lp', 'gp', 'bt', 'psa');

    /**
     * Fetch a single business's record
     *
     * @return array
     */
    function fetch()
    {

        if (!isset($this->db) || !isset($this->id))
        {
            return FALSE;
        }

        /*
         * Which table holds a record can no longer be inferred from its ID.
         * Historically the first character was decisive (S/T meant an LLC, L/M a
         * limited partnership, a digit or F a corporation), but current SCC data
         * puts digit-prefixed IDs in all three tables and mixes the letter
         * prefixes across them, which left roughly 38% of records unreachable.
         *
         * IDs do not collide between the three tables, so asking each one in
         * turn is unambiguous. type_from_id() still supplies the order, so the
         * likeliest table is tried first and the common case costs one query.
         */
        $this->business = NULL;
        $candidates = self::ENTITY_TABLES;
        $likeliest = $this->type_from_id($this->id);

        if ($likeliest !== FALSE)
        {
            $candidates = array_merge(
                array($likeliest),
                array_diff($candidates, array($likeliest))
            );
        }

        foreach ($candidates as $candidate)
        {
            $this->business = $this->fetch_from($candidate, $this->id);

            if (is_array($this->business))
            {
                $this->type = $candidate;
                break;
            }
        }

        if (!is_array($this->business))
        {
            return FALSE;
        }

        foreach ($this->business as &$field)
        {
            $field = trim($field);
        }
        unset($field);

        $lookup_table = Business::lookup_table();

        /*
         * Translate coded values into their descriptions. Historical data stores
         * numeric codes ("00"), which these lookups expand; current SCC exports
         * ship the description in the column itself ("INACTIVE", "0 - General"),
         * in which case there is nothing to look up and the raw value stands.
         * Records missing the field entirely resolve to an empty string.
         */
        $lookups = array(
            'StatusText'    => array('corporate-status-table', 'Status'),
            'IndustryText'  => array('industry-code-table', 'IndustryCode'),
            'RA-StatusText' => array('registered-agent-status', 'RA-Status'),
            'RA-LocText'    => array('court-locality-code', 'RA-Loc'),
            'AssessIndText' => array('assessment-indicator', 'AssessInd'),
        );

        foreach ($lookups as $target => $lookup)
        {
            list($table, $column) = $lookup;
            $code = $this->business[$column] ?? '';
            $this->business[$target] = $lookup_table[$table][$code] ?? $code;
        }
        
        return $this->business;

    }

    /**
     * Fetch a single record from one entity table
     *
     * @param string $table one of ENTITY_TABLES
     * @param string $id the entity identifier
     * @return array|false the record, or FALSE if this table has no such row
     */
    private function fetch_from($table, $id)
    {

        /*
         * SQLite cannot bind a table name, so this is interpolated -- which is
         * safe only because the value is checked against the allowlist first.
         */
        if (!in_array($table, self::ENTITY_TABLES, TRUE))
        {
            return FALSE;
        }

        $sql = 'SELECT *,

                    (SELECT Description
                    FROM tables
                    WHERE tables.TableID="01"
                    AND tables.ColumnID="Status"
                    AND tables.ColumnValue=' . $table . '.Status) StatusText,

                    (SELECT tables.Description
                    FROM tables
                    WHERE tables.TableID="03"
                    AND tables.ColumnID="IndustryCode"
                    AND tables.ColumnValue=' . $table . '.IndustryCode) Industry,

                    (SELECT tables.Description
                    FROM tables
                    WHERE tables.TableID="07"
                    AND tables.ColumnID="AssessInd"
                    AND tables.ColumnValue=' . $table . '.AssessInd) "AssessIndText"

                FROM ' . $table . '
                WHERE EntityID = :id
                LIMIT 1';

        $statement = $this->db->prepare($sql);
        if ($statement === FALSE)
        {
            return FALSE;
        }
        $statement->bindValue(':id', $id, SQLITE3_TEXT);

        $result = $statement->execute();
        if ($result === FALSE)
        {
            return FALSE;
        }

        $record = $result->fetchArray(SQLITE3_ASSOC);

        /*
         * A SELECT that matches nothing still reports its column count, so the
         * fetch result is the only reliable emptiness test.
         */
        return is_array($record) ? $record : FALSE;

    }

     /**
      * Search matching business records, return the first 99
      *
      * @return array
      */
    function search()
    {

        if (!isset($this->db) || !isset($this->query))
        {
            return FALSE;
        }

        $this->results = [];

        /*
         * Escape the LIKE metacharacters in the user's query. Binding the value
         * stops it from breaking out of the string, but "%" and "_" are still
         * wildcards *within* that string -- a search for "%" would otherwise
         * match every record in the table.
         */
        $pattern = str_replace(
            array('\\', '%', '_'),
            array('\\\\', '\\%', '\\_'),
            $this->query
        );

        foreach (self::ENTITY_TABLES as $type)
        {

            $sql = 'SELECT *
                    FROM ' . $type . '
                    WHERE Name LIKE :pattern ESCAPE \'\\\'
                    LIMIT 33';

            $statement = $this->db->prepare($sql);
            if ($statement === FALSE)
            {
                continue;
            }
            $statement->bindValue(':pattern', '%' . $pattern . '%', SQLITE3_TEXT);

            $result = $statement->execute();
            if ($result === FALSE)
            {
                continue;
            }

            while ($business = $result->fetchArray(SQLITE3_ASSOC))
            {
                $this->results[] = $business;
            }
        }

        return $this->results;

    }

    /**
     * Verify that a business identifier is syntatically valid
     *
     * @param [type] $id
     * @return boolean
     */
    function id_is_valid($id)
    {
        /*
         * The SCC lengthened entity IDs from 7 to 8 characters (the old
         * "F000032" is now "F0000325"), so accept either width.
         */
        $entity_id_pcre = '/^[A-Z0-9][0-9]{6,7}$/i';

        if ( preg_match($entity_id_pcre, $id) == 0 )
        {
            return false;
        }
        return true;
    }

    /**
     * Identify type of business based on identifier
     *
     * @param [type] $id
     * @return type as string
     */
    function type_from_id($id)
    {
        if ( !isset($id) )
        {
            return false;
        }

        /*
         * IDs are 7 characters in the historical data and 8 in current SCC
         * exports; anything else is not an entity ID.
         */
        $id = trim($id);
        if ( strlen($id) < 7 || strlen($id) > 8 )
        {
            return false;
        }

        $id = strtolower($id);

        /*
         * Get the first character
         */
        $first = substr($id, 0, 1);
        
        if ( ($first == 's') || ($first == 't') )
        {
            return 'llc';
        }
        elseif ( ($first == 'l') || ($first == 'm') )
        {
            return 'lp';
        }
        elseif ( $first == 'f' || is_numeric($first) )
        {
            return 'corp';
        }
        else
        {
            return false;
        }

    }

    /**
     * List businesses created in the past week
     *
     * @return array
     */
    function recent()
    {
        $sql = 'SELECT *
                FROM corp
                ORDER BY IncorpDate DESC
                LIMIT 100';
        
        $result = $this->db->query($sql);

        if ($result->numColumns() == 0)
        {
            return false;
        }

        $this->results = [];
        while ($business = $result->fetchArray(SQLITE3_ASSOC))
        {
            $this->results[] = $business;
        }

        return $this->results;
    }

    function lookup_table()
    {
        /*
        * Fetch the conversion table
        */
        $tables_json = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/includes/tables.json');

        /*
        * Convert to an array
        */
        $tables = json_decode($tables_json, TRUE);

        $this->lookup_table = array();

        /*
        * Reduce and pivot the table into a nested key/value lookup
        */
        foreach ($tables as $entry)
        {
            unset($entry['TableID']);
            $entry['TableContents'] = strtolower($entry['TableContents']);
            $entry['TableContents'] = preg_replace('/[\&\.\/]/', '', $entry['TableContents']);
            $entry['TableContents'] = preg_replace('/\W+/', '-', $entry['TableContents']);

            if (!isset($this->lookup_table[$entry['TableContents']]))
            {
                $this->lookup_table[$entry['TableContents']] = array();
            }

            $this->lookup_table[$entry['TableContents']][$entry['ColumnValue']] = $entry['Description'];
        }
        
        return $this->lookup_table;
    }

}
