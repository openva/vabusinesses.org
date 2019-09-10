<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/autoloader.php';

/*
 * If there's a 7-character ID, use that.
 */
if ( isset($_GET['id']) && strlen($_GET['id']) == 7)
{
    $id = $_GET['id'];
}

$database = new Database;
$db = $database->connect();

$business = new Business;
$business->db = $db;
$business->id = $id;
$biz = $business->fetch();

echo json_encode($biz);
