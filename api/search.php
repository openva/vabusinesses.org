<?php

/*
 * The router hands back the path segment exactly as it appeared in the URL, so
 * a search for "american brands" arrives as "american%20brands" and would be
 * looked up literally. Decode it before use.
 *
 * It is deliberately not HTML-escaped here. This value is only ever used to
 * query the database -- the response is JSON, and json_encode() escapes what it
 * emits -- so escaping first would mean searching for "Book N&#039; Scoop"
 * rather than "Book N' Scoop". Business::search() binds the term to a prepared
 * statement and escapes the LIKE metacharacters, which is what makes it safe.
 */
$query = rawurldecode($query ?? '');

$database = new Database;
$db = $database->connect();

if (!$db)
{
    header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
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
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", true, 404);
    echo json_encode('Error');
    exit;
}

echo json_encode($results);
