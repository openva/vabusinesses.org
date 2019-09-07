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

        if (!isset($db))
        {
            return FALSE;
        }

        if (!isset($id))
        {
            return FALSE;
        }

        $sql = 'SELECT *
                FROM corp
                WHERE EntityID="' . $id . '"';
        $result = $this->db->query($sql);

        if ($result->rowCount() == 0)
        {
            return false;
        }
        $this->business = $result->fetchArray();

        return $this->business;

    }

}
