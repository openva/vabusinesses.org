<?php

/**
 * Interact with business-related data
 **/
class Officers
{

    /*
     * Declared explicitly because PHP 8.2 deprecates dynamic properties.
     */
    public $db;
    public $id;
    public $officers;

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
                WHERE EntityID = :id
                ORDER BY OfficerLastName ASC';

        if ($result->numColumns() == 0)
        $statement = $this->db->prepare($sql);
        if ($statement === FALSE)
        {
            return false;
            return FALSE;
        }
        $statement->bindValue(':id', $this->id, SQLITE3_TEXT);

        $result = $statement->execute();
        if ($result === FALSE)
        {
            return FALSE;
        }

        $this->officers = array();
        while ($officer = $result->fetchArray(SQLITE3_ASSOC))
        {
            $this->officers[] = $officer;
        }
        return $this->officers;

    }
}
