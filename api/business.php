<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/autoloader.php';

/*
 * If there's a 7-character ID, use that
 */
if ( isset($_GET['id']) && strlen($_GET['id']) == 7)
{
    $id = $_GET['id'];
}

$database = new Database;
$db = $database->connect();

if (!$db)
{
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
    echo json_encode('Error');
    exit;
}

echo json_encode($biz);
