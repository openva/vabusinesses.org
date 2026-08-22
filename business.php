<?php

include('includes/header.php');

/*
 * Define the PCRE to match all entity IDs
 */
$entity_id_pcre = '/^[A-Z0-9][0-9]{6,7}$/i';  // see Business::id_is_valid()

/*
 * If no business ID has been passed in the URL
 */
if (!isset($id))
{
    header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", true, 400);
    exit();
}

/*
 * If the business ID has an invalid format
 */
elseif ( preg_match($entity_id_pcre, $id) == 0 )
{
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", true, 404);
    exit();
}

/*
 * Query our own API 
 */
$api_url = API_URL . '/api/business/' . rawurlencode($id);

$business_json = get_content($api_url);
$business = json_decode($business_json, true);
if ($business_json === false || $business === null)
{
    header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
    exit();
}

/*
 * The API reports a missing record as the JSON string "Error", which decodes to
 * a string rather than to FALSE or to an empty value -- so test for a record
 * shaped like one, instead of letting a string reach $business['Name'].
 */
elseif (!is_array($business) || empty($business['Name']))
{
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", true, 404);
    exit();
}

$template = new Smarty;

$page_title = $business['Name'];
$browser_title = 'Virginia Businesses';

/*
 * Display a table of all field values
 */
$page_body = '
<article>
    <table>
        <thead>
            <tr>
                <th scope="col"></th>
                <th scope="col"></th>
            </tr>
        </thead>
        <tbody>';

$field_order = array
(
    'Name' => 'Name',
    'EntityID' => 'Corporate ID',
    'IncorpDate' => 'Incorporation Date',
    'IncorpState' => 'State Incorporated',
    'IndustryText' => 'Industry',
    'Status' => 'Status',
    'StatusDate' => 'Date',
    'Duration' => 'Duration',
    'Street1' => 'Street Address',
    'Street2' => 'Street Address 2',
    'City' => 'City',
    'State' => 'State',
    'Zip' => 'ZIP',
    'PrinOffEffDate' => 'PrinOffEffDate',
    'AssessIndText' => 'AssessInd',
    'StockInd' => 'StockInd',
    'TotalShares' => 'Total Shares',
    'Stock1' => 'Stock1',
    'Stock2' => 'Stock2',
    'Stock3' => 'Stock3',
    'Stock4' => 'Stock4',
    'Stock5' => 'Stock5',
    'Stock6' => 'Stock6',
    'Stock7' => 'Stock7',
    'Stock8' => 'Stock8',
    'Stock9' => 'Stock9',
);

$business['IncorpDate'] = date('M d, Y', strtotime($business['IncorpDate']));

foreach ($field_order as $key => $label)
{
    if (empty($business[$key]))
    {
        continue;
    }

    $page_body .= '<tr><td>' . $label . '</td><td>' . $business[$key] . '</td></tr>';
}

foreach ($business as $field_name => $field_value)
{

    if ( is_array($field_value) )
    {

        if ($field_name == 'Officers')
        {
            foreach ($field_value as $officer)
            {
                $page_body .= '<tr><td>' . $officer['OfficerTitle'] . '</td><td>' . $officer['OfficerFirstName'] . ' ' . $officer['OfficerLastName'] . '</td></tr>';
            }
        }

        elseif ($field_name == 'RegisteredAgent')
        {
            foreach ($field_value as $key => $value)
            {
                $page_body .= '<tr><td>Registered Agent ' . $key . '</td><td>' . $value . '</td></tr>';
            }
        }
    }
}
$page_body .= '
    </table>
</article>';

$template->assign('page_body', $page_body);
$template->assign('page_title', $page_title);
$template->assign('browser_title', $browser_title);

$template->display('includes/templates/simple.tpl');
