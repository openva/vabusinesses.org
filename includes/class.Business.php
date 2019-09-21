<?php

/**
 * Interact with business-related data
 **/
class Business
{

    /*
     * Fetch a single business's record
     */
    function fetch()
    {

        if (!isset($this->db) || !isset($this->id))
        {
            return FALSE;
        }

        $sql = 'SELECT *,

                    (SELECT tables.Description
                    FROM tables
                    WHERE tables.TableID="01"
                    AND tables.ColumnID="Status"
                    AND tables.ColumnValue=corp.Status) StatusText,

                    (SELECT tables.Description
                    FROM tables
                    WHERE tables.TableID="03"
                    AND tables.ColumnID="IndustryCo"
                    AND tables.ColumnValue=corp.IndustryCode) Industry,

                    (SELECT tables.Description
                    FROM tables
                    WHERE tables.TableID="04"
                    AND tables.ColumnID="RA-Status"
                    AND tables.ColumnValue="corp.RA-Status") "RA-StatusText",

                    (SELECT tables.Description
                    FROM tables
                    WHERE tables.TableID="05"
                    AND tables.ColumnID="RA-Localit"
                    AND tables.ColumnValue="corp.RA-Loc") "RA-LocText",

                    (SELECT tables.Description
                    FROM tables
                    WHERE tables.TableID="07"
                    AND tables.ColumnID="AssessInd"
                    AND tables.ColumnValue="corp.AssessInd") "AssessIndText"

                FROM corp
                WHERE EntityID="' . $this->id . '"';
        $result = $this->db->query($sql);

        if ($result->numColumns() == 0)
        {
            return false;
        }
        $this->business = $result->fetchArray(SQLITE3_ASSOC);

        return $this->business;

    }

    /*
     * Search matching business records, return the first 100
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

}
