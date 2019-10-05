<?php

$query = filter_var($query, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

$database = new Database;
$db = $database->connect();

if (!$db)
{
    header($_SERVER["SERVER_PROTOCOL"]." 500 Internal Server Error", true, 500);
    echo json_encode('Error');
    exit;
}

/*
 * Get the first 50 matching records
 */
$business = new Business;
$business->db = $db;
$business->query = $query;
$results = $business->search();

if (!is_array($results))
{
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
    echo json_encode('Error');
    exit;
}

echo json_encode($results);
