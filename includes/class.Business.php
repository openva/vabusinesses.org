<?php

/**
 * Interact with business-related data
 **/
class Business
{

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

        $this->type = $this->type_from_id($this->id);
        if ($this->type === FALSE)
        {
            return FALSE;
        }

        $sql = 'SELECT *,

                    (SELECT Description
                    FROM tables
                    WHERE tables.TableID="01"
                    AND tables.ColumnID="Status"
                    AND tables.ColumnValue=' . $this->type . '.Status) StatusText,

                    (SELECT tables.Description
                    FROM tables
                    WHERE tables.TableID="03"
                    AND tables.ColumnID="IndustryCo"
                    AND tables.ColumnValue=' . $this->type . '.IndustryCode) Industry,

                    (SELECT tables.Description
                    FROM tables
                    WHERE tables.TableID="04"
                    AND tables.ColumnID="RA-Status"
                    AND tables.ColumnValue="' . $this->type . '.RA-Status") "RA-StatusText",

                    (SELECT tables.Description
                    FROM tables
                    WHERE tables.TableID="05"
                    AND tables.ColumnID="RA-Localit"
                    AND tables.ColumnValue="' . $this->type . '.RA-Loc") "RA-LocText",

                    (SELECT tables.Description
                    FROM tables
                    WHERE tables.TableID="07"
                    AND tables.ColumnID="AssessInd"
                    AND tables.ColumnValue=' . $this->type . '.AssessInd) "AssessIndText"

                FROM ' . $this->type . '
                WHERE EntityID="' . $this->id . '"';
        $result = $this->db->query($sql);

        if ($result->numColumns() == 0)
        {
            return false;
        }
        $this->business = $result->fetchArray(SQLITE3_ASSOC);
        
        foreach ($this->business as &$field)
        {
            $field = trim($field);
        }

        $lookup_table = Business::lookup_table();
        $this->business['StatusText'] = $lookup_table['corporate-status-table'][$this->business{'Status'}];
        $this->business['IndustryText'] = $lookup_table['industry-code-table'][$this->business{'IndustryCode'}];
        $this->business['RA-StatusText'] = $lookup_table['registered-agent-status'][$this->business{'RA-Status'}];
        $this->business['RA-LocText'] = $lookup_table['court-locality-code'][$this->business{'RA-Localit'}];
        $this->business['AssessIndText'] = $lookup_table['assessment-indicator'][$this->business{'AssessInd'}];
        
        return $this->business;

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

        foreach (array('corp', 'llc', 'lp') as $type)
        {

            $sql = 'SELECT *
                    FROM ' . $type . '
                    WHERE Name LIKE "%' . $this->query . '%"
                    LIMIT 33';
            
            $result = $this->db->query($sql);

            if ($result->numColumns() == 0)
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
        $entity_id_pcre = '/(F|S|T|L|M|[0-9]{1})([0-9]{6})/';

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
        if ( !isset($id) || strlen($id) <> 7 )
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

            if (!isset($this->lookup_table[$entry{'TableContents'}]))
            {
                $this->lookup_table[$entry{'TableContents'}] = array();
            }

            $this->lookup_table[$entry{'TableContents'}][$entry{'ColumnValue'}] = $entry['Description'];
        }
        
        return $this->lookup_table;
    }

}
