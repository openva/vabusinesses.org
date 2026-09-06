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
 * Filtering and ordering come from the query string rather than the path, so
 * that the route keeps matching on the search term alone. Each value is
 * validated inside search(), which falls back to a default rather than
 * erroring on anything it does not recognise.
 */
$status = strtoupper(trim($_GET['status'] ?? ''));
$sort   = strtolower(trim($_GET['sort'] ?? 'name'));
$order  = strtolower(trim($_GET['order'] ?? ''));

/*
 * Newest-first is the useful default for a date sort and alphabetical for a
 * name sort, so the direction follows the column unless it is given.
 */
if ($order === '')
{
    $order = ($sort === 'date') ? 'desc' : 'asc';
}

$business = new Business;
$business->db = $db;
$business->query = $query;
$results = $business->search($status, $sort, $order);

if (!is_array($results))
{
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", true, 404);
    echo json_encode('Error');
    exit;
}

echo json_encode($results);
