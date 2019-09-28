<?php

include('vendor/autoload.php');

/*
 * Define the function for API queries, etc.
 */
function get_content($url)
{

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $string = curl_exec($ch);
    curl_close($ch);

    if (empty($string))
    {
        return FALSE;
    }

    return $string;

}

/*
 * Identify the prefix for URL queries
 */
if (!empty($_SERVER['HTTPS']))
{
    $api_url = 'https';
}
else {
    $api_url = 'http';
}
$api_url .= '://';
$api_url .= $_SERVER['SERVER_NAME'];
define('API_URL', $api_url);

/*
 * Fetch the conversion table
 */
$tables_json = file_get_contents('includes/tables.json');

/*
 * Convert to an array
 */
$tables = json_decode($tables_json, TRUE);

$lookup_table = array();

/*
 * Reduce and pivot the table into a nested key/value lookup
 */
foreach ($tables as $entry)
{
    unset($entry['TableID']);
    $entry['TableContents'] = strtolower($entry['TableContents']);
    $entry['TableContents'] = preg_replace('/[\&\.\/]/', '', $entry['TableContents']);
    $entry['TableContents'] = preg_replace('/\W+/', '-', $entry['TableContents']);

    if (!isset($lookup_table[$entry{'TableContents'}]))
    {
        $lookup_table[$entry{'TableContents'}] = array();
    }

    $lookup_table[$entry{'TableContents'}][$entry{'ColumnValue'}] = $entry['Description'];
}
