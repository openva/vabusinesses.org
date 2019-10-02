<?php

/**
 * Interact with business-related data
 **/
class Officers
{

    /**
     * Fetch a single business's officers records
     *
     * @return array
     */
    function fetch()
    {

        if (!isset($this->db) || !isset($this->id))
        {
            return FALSE;
        }

        $sql = 'SELECT OfficerTitle, OfficerFirstName, OfficerMiddleName,
                    OfficerLastName
                FROM officer
                WHERE EntityID="' . $this->id . '"
                ORDER BY OfficerLastName ASC';
        $result = $this->db->query($sql);

        if ($result->numColumns() == 0)
        {
            return false;
        }
        
        $this->officers = array();
        while ($officer = $result->fetchArray(SQLITE3_ASSOC))
        {
            $this->officers[] = $officer;
        }
        return $this->officers;

    }
}
