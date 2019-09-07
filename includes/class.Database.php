<?php

class Database
{

    /*
     * Create a database connection
     */
    function connect()
    {
        $this->file = $_SERVER['DOCUMENT_ROOT'] . '/data/vabusinesses.sqlite';
        $this->db = new SQLite3($this->file);
        return $this->db;
    }
    
}
