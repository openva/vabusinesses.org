<?php

/**
 * Interact with business-related data
 **/
class Business
{

    /**
     * Fetch a single business's record
    *
    * @return void
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

        return $this->business;

    }

     /**
      * Search matching business records, return the first 100
      *
      * @return array
      */
    function search()
    {

        if (!isset($this->db) || !isset($this->query))
        {
            return FALSE;
        }

        $sql = 'SELECT *
                FROM corp
                WHERE Name LIKE "%' . $this->query . '%"
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

}
