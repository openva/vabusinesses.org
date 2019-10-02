<?php

/*
 * Use the identifier passed in the URL
 */
if ( !isset($id) || strlen($id) != 7)
{
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
    echo json_encode('Error');
    exit;
}

$database = new Database;
$db = $database->connect();

if (!$db)
{
    header($_SERVER["SERVER_PROTOCOL"]." 500 Internal Server Error", true, 500);
    echo json_encode('Error');
    exit;
}

/*
 * Get the business record
 */
$business = new Business;
$business->db = $db;
$business->id = $id;
$biz = $business->fetch();

if (!is_array($biz))
{
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
    echo json_encode('Error');
    exit;
}

/*
 * Get the officer records
 */
$officers = new Officers;
$officers->db = $db;
$officers->id = $id;
$biz['Officers'] = $officers->fetch();

echo json_encode($biz);
