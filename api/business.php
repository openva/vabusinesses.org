<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';

/*
 * Use the identifier passed in the URL
 */
if ( isset($_GET['id']) && strlen($_GET['id']) == 7)
{
    $id = $_GET['id'];
}
else
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

echo json_encode($biz);
