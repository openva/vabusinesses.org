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

        $sql = 'SELECT *
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
