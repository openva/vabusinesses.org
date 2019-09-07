<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/autoloader.php';

$database = new Database;
$db = $database->connect();

$business = new Business;
$business->db = $db;
$business->id = '0530258';
$biz = $business->fetch();

echo json_encode($biz);
