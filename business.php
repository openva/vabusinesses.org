<?php

include('includes/header.php');

/*
 * Define the PCRE to match all entity IDs
 */
$entity_id_pcre = '/(F|S|T|L|M|[0-9]{1})([0-9]{6})/';

/*
 * If no business ID has been passed in the URL
 */
if (!isset($id))
{
    header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request", true, 400);
    exit();
}

/*
 * If the business ID has an invalid format
 */
elseif ( preg_match($entity_id_pcre, $id) == 0 )
{
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
    exit();
}

/*
 * Query our own API 
 */
$api_url = API_URL . '/api/business/' . $id;

$business_json = get_content($api_url);
$business = json_decode($business_json, TRUE);
if ($business === FALSE)
{
    header($_SERVER["SERVER_PROTOCOL"]." 500 Internal Server Error", true, 500);
    exit();
}

elseif (empty($business))
{
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
    exit();   
}

$template = new Smarty;

$page_title = 'Virginia Businesses';
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
foreach ($business as $field_name => $field_value)
{
    
    /*
     * Ignore blank stock fields
     */
    if ( stristr($field_name, 'stock') && empty($field_value) )
    {
        continue;
    }

    if ( !is_array($field_value) )
    {
        $page_body .= '<tr><td>' . $field_name . '</td><td>' . $field_value . '</td></tr>';
    }

    elseif ($field_name == 'Officers')
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
$page_body .= '
    </table>
</article>';

$template->assign('page_body', $page_body);
$template->assign('page_title', $page_title);
$template->assign('browser_title', $browser_title);

$template->display('includes/templates/simple.tpl');
