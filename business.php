<?php

function get_content($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $URL);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

/*
 * If no business ID has been passed in the URL
 */
if (!isset($_GET['id']))
{
    header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request", true, 400);
    exit();
}

/*
 * If the business ID has an invalid format
 */
elseif ( strlen($_GET['id']) < 7 || strlen($_GET['id']) > 9 )
{
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
    exit();
}

$id = $_GET['id'];

/*
 * Query our own API 
 */
$api_url = 'https://vabusinesses.org/api/business/' . $id;
$business_json = get_content($api_url);

$business = json_decode($business_json);
if ($business === FALSE)
{
    header($_SERVER["SERVER_PROTOCOL"]." 500 Internal Server Error", true, 500);
    exit();
}

/*
 * Display a table of all field values
 */
echo '<table>';
foreach ($business as $field_name => $field_value)
{
    echo '<tr><td>';
    echo $field_name;
    echo '</td><td>';
    echo $field_value;
    echo '</td></tr>';
}
echo '</table>';
