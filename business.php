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
/*
 * Render the record as a set of labelled sections rather than one long,
 * unlabelled two-column table. Fields are grouped by what they describe, empty
 * values are skipped, and a few are formatted for reading rather than shown as
 * the SCC stores them.
 */
function detail_row($label, $value)
{
    if ($value === NULL || trim((string) $value) === '')
    {
        return '';
    }

    return "\n\t\t\t<tr><th scope=\"row\">"
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</th><td>'
        . nl2br(htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8'))
        . '</td></tr>';
}

function detail_section($caption, $rows)
{
    if (trim($rows) === '')
    {
        return '';
    }

    return "\n\t<table class=\"detail\">\n\t\t<caption>"
        . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8')
        . "</caption>\n\t\t<tbody>" . $rows . "\n\t\t</tbody>\n\t</table>";
}

/*
 * "9999-12-31" is the SCC's sentinel for a duration that does not expire.
 */
$duration = $business['Duration'] ?? '';
if ($duration === '9999-12-31')
{
    $duration = 'Perpetual';
}
elseif ($duration !== '')
{
    $duration = date('F j, Y', strtotime($duration));
}

$address = array_filter(array(
    $business['Street1'] ?? '',
    $business['Street2'] ?? '',
    trim(($business['City'] ?? '') . ', ' . ($business['State'] ?? ''), ' ,'),
    $business['Zip'] ?? '',
));

$shares = $business['TotalShares'] ?? '';
if ($shares !== '' && is_numeric($shares))
{
    $shares = number_format((float) $shares);
}

$stock = array();
foreach (range(1, 9) as $n)
{
    $value = trim($business['Stock' . $n] ?? '');
    if ($value !== '')
    {
        $stock[] = $value;
    }
}

$page_body = '<article>';

/*
 * Identity
 */
$rows  = detail_row('Entity ID', $business['EntityID'] ?? '');
$rows .= detail_row('Status', $business['StatusText'] ?: ($business['Status'] ?? ''));
$rows .= detail_row('Status reason', $business['StatusReason'] ?? '');
$status_date = $business['Status Date'] ?? ($business['StatusDate'] ?? '');
if ($status_date !== '')
{
    $status_date = date('F j, Y', strtotime($status_date));
}
$rows .= detail_row('Status date', $status_date);
$rows .= detail_row('Industry', $business['IndustryText'] ?: ($business['IndustryCode'] ?? ''));
$incorporated = $business['IncorpDate'] ?? '';
if ($incorporated !== '')
{
    $incorporated = date('F j, Y', strtotime($incorporated));
}
$rows .= detail_row('Incorporated', $incorporated);
$rows .= detail_row('State of incorporation', $business['IncorpState'] ?? '');
$rows .= detail_row('Duration', $duration);
$page_body .= detail_section('Registration', $rows);

/*
 * Address
 */
$rows = detail_row('Principal office', implode("\n", $address));
$page_body .= detail_section('Address', $rows);

/*
 * A locator map, when this address has been geocoded and a tile provider is
 * configured. Both are optional: data/addresses.db is populated gradually and is
 * not part of the deployment, and MAPBOX_TOKEN comes from the server's
 * environment. Absent either, the page simply has no map.
 */
if (MAP_TILES !== '' && !empty($address))
{
    $geocode = new Geocode;

    if ($geocode->connect() !== FALSE)
    {
        $point = $geocode->coordinates(
            $business['Street1'] ?? '',
            $business['Street2'] ?? '',
            $business['City'] ?? '',
            $business['State'] ?? '',
            $business['Zip'] ?? ''
        );

        if ($point !== FALSE)
        {
            $page_body .= "\n\t" . '<div id="map"'
                . ' data-latitude="' . htmlspecialchars((string) $point['latitude'], ENT_QUOTES, 'UTF-8') . '"'
                . ' data-longitude="' . htmlspecialchars((string) $point['longitude'], ENT_QUOTES, 'UTF-8') . '"'
                . ' data-tiles="' . htmlspecialchars(MAP_TILES, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-attribution="' . htmlspecialchars(MAP_ATTRIBUTION, ENT_QUOTES, 'UTF-8') . '"'
                . '></div>';
        }
    }
}

/*
 * Stock
 */
$rows  = detail_row('Total shares', $shares);
$rows .= detail_row('Share classes', implode(', ', $stock));
$page_body .= detail_section('Stock', $rows);

/*
 * Assessment applies to every entity type, not just those with stock, so it is
 * not grouped under "Stock" -- partnerships have an assessment and no shares.
 */
$page_body .= detail_section('Assessment', detail_row('Type', $business['AssessIndText'] ?? ''));

/*
 * Registered agent. The API returns both the raw code and its expansion for
 * some fields; only the expansion is worth showing, and only when it differs.
 */
if (!empty($business['RegisteredAgent']) && is_array($business['RegisteredAgent']))
{
    $agent = $business['RegisteredAgent'];

    $agent_address = array_filter(array(
        $agent['Street1'] ?? '',
        $agent['Street2'] ?? '',
        trim(($agent['City'] ?? '') . ', ' . ($agent['State'] ?? ''), ' ,'),
        $agent['Zip'] ?? '',
    ));

    $rows  = detail_row('Name', preg_replace('/\s+/', ' ', $agent['Name'] ?? ''));
    $rows .= detail_row('Address', implode("\n", $agent_address));
    $rows .= detail_row('Status', $agent['StatusText'] ?: ($agent['Status'] ?? ''));
    /*
     * No fallback to the raw Loc code: it is a bare number with no meaning to a
     * reader, and the agent's city is shown above it in any case.
     */
    $rows .= detail_row('Locality', $agent['LocText'] ?? '');
    $rows .= detail_row('Effective', $agent['EffDate'] ?? '');
    $page_body .= detail_section('Registered agent', $rows);
}

/*
 * Officers and directors
 */
if (!empty($business['Officers']) && is_array($business['Officers']))
{
    /*
     * Officers are a list of people, not labelled fields, so they get a real
     * two-column table with headers. Many records carry no title at all, and the
     * SCC truncates the ones it does store ("Treasurer  Offi"), so an absent
     * title is left blank rather than invented.
     */
    $rows = '';
    foreach ($business['Officers'] as $officer)
    {
        $name = preg_replace('/\s+/', ' ', trim(
            ($officer['OfficerFirstName'] ?? '') . ' ' .
            ($officer['OfficerMiddleName'] ?? '') . ' ' .
            ($officer['OfficerLastName'] ?? '')
        ));

        if ($name === '')
        {
            continue;
        }

        $title = preg_replace('/\s+/', ' ', trim($officer['OfficerTitle'] ?? ''));

        $rows .= "\n\t\t\t<tr><td>"
            . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            . '</td><td>'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</td></tr>';
    }

    if ($rows !== '')
    {
        $page_body .= "\n\t<table>\n\t\t<caption>Officers and directors</caption>"
            . "\n\t\t<thead>\n\t\t\t<tr><th scope=\"col\">Name</th>"
            . "<th scope=\"col\">Title</th></tr>\n\t\t</thead>"
            . "\n\t\t<tbody>" . $rows . "\n\t\t</tbody>\n\t</table>";
    }
}

$page_body .= "\n</article>";

$template->assign('needs_map', strpos($page_body, 'id="map"') !== FALSE);
$template->assign('page_body', $page_body);
$template->assign('page_title', $page_title);
$template->assign('browser_title', $browser_title);

$template->display('includes/templates/simple.tpl');
