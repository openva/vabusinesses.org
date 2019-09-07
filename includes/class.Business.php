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

        if (!isset($this->db))
        {
            return FALSE;
        }

        if (!isset($this->id))
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
        $this->business = $result->fetchArray();

        return $this->business;

    }

}
