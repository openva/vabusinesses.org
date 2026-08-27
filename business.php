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

/**
 * Wrap a set of rows in a titled section.
 *
 * The title is an <h2> inside a <section>, not a <caption>: a caption belongs to
 * its table and does not appear in the document outline, so a page built from
 * captions alone offers a screen reader nothing to navigate between. The table
 * keeps a caption too, hidden visually, so it is still labelled in isolation.
 */
function detail_section($title, $rows, $id = '')
{
    if (trim($rows) === '')
    {
        return '';
    }

    $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $attribute = $id === '' ? '' : ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"';

    return "\n\t<section" . $attribute . ">"
        . "\n\t\t<h2>" . $title . "</h2>"
        . "\n\t\t<table class=\"detail\">"
        . "\n\t\t\t<caption class=\"visually-hidden\">" . $title . "</caption>"
        . "\n\t\t\t<tbody>" . $rows . "\n\t\t\t</tbody>"
        . "\n\t\t</table>\n\t</section>";
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

/*
 * Entity types, keyed by the table the record came from.
 */
$entity_types = array(
    'corp' => 'Corporation',
    'llc'  => 'Limited liability company',
    'lp'   => 'Limited partnership',
    'gp'   => 'General partnership',
    'bt'   => 'Business trust',
    'psa'  => 'Public service authority',
);

$entity_type = $entity_types[$business['EntityType'] ?? ''] ?? '';

/*
 * Status and status reason overlap -- "ACTIVE" and "Active and In Good
 * Standing" -- so the longer of the two carries the meaning, with the date
 * folded in rather than given a row of its own.
 */
$status = trim($business['StatusReason'] ?? '') ?: trim($business['StatusText'] ?? '');

$status_date = $business['Status Date'] ?? ($business['StatusDate'] ?? '');
if ($status_date !== '')
{
    $status_date = date('F j, Y', strtotime($status_date));
}

$status_line = $status;
if ($status !== '' && $status_date !== '')
{
    $status_line = $status . ' (since ' . $status_date . ')';
}

$incorporated = $business['IncorpDate'] ?? '';
if ($incorporated !== '')
{
    $incorporated = date('F j, Y', strtotime($incorporated));
}

/*
 * A one-line answer to the questions most people arrive with: is this business
 * active, what kind of business is it, and where is it.
 */
$locality = trim(($business['City'] ?? '') . ', ' . ($business['State'] ?? ''), ' ,');

$summary = array_filter(array(
    trim($business['StatusText'] ?? '') ?: $status,
    $entity_type,
    $locality,
));

$page_summary = implode(' &middot; ', array_map(
    function ($part) { return htmlspecialchars($part, ENT_QUOTES, 'UTF-8'); },
    $summary
));

$page_body = '';

/*
 * Location first: the map is the most informative thing on the page, and the
 * address is what most people are looking for.
 */
$rows = detail_row('Address', implode("\n", $address));

$map = '';

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
            $map = "\n\t\t" . '<div id="map"'
                . ' data-latitude="' . htmlspecialchars((string) $point['latitude'], ENT_QUOTES, 'UTF-8') . '"'
                . ' data-longitude="' . htmlspecialchars((string) $point['longitude'], ENT_QUOTES, 'UTF-8') . '"'
                . ' data-tiles="' . htmlspecialchars(MAP_TILES, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-attribution="' . htmlspecialchars(MAP_ATTRIBUTION, ENT_QUOTES, 'UTF-8') . '"'
                . '></div>';
        }
    }
}

if (trim($rows) !== '' || $map !== '')
{
    $page_body .= "\n\t<section id=\"location\">\n\t\t<h2>Location</h2>" . $map;

    if (trim($rows) !== '')
    {
        $page_body .= "\n\t\t<table class=\"detail\">"
            . "\n\t\t\t<caption class=\"visually-hidden\">Location</caption>"
            . "\n\t\t\t<tbody>" . $rows . "\n\t\t\t</tbody>"
            . "\n\t\t</table>";
    }

    $page_body .= "\n\t</section>";
}

/*
 * Registration. Entity ID comes last: it is a lookup key, not something anyone
 * reads a page to find out.
 */
$rows  = detail_row('Incorporated', $incorporated);
$rows .= detail_row('Status', $status_line);
$rows .= detail_row('Type', $entity_type);
$rows .= detail_row('Industry', $business['IndustryText'] ?: ($business['IndustryCode'] ?? ''));
$rows .= detail_row('Duration', $duration);
$rows .= detail_row('Chartered in', $business['IncorpState'] ?? '');
$rows .= detail_row('Entity ID', $business['EntityID'] ?? '');

/*
 * A link back to the SCC's own record, so that anybody can check this page
 * against the source. The SCC's "businessId" is the same value as EntityID.
 *
 * detail_row() escapes its value, so the anchor is assembled here instead.
 */
$entity_id = trim($business['EntityID'] ?? '');

if ($entity_id !== '')
{
    $rows .= "\n\t\t\t\t<tr><th scope=\"row\">Official record</th><td>"
        . '<a href="https://cis.scc.virginia.gov/EntitySearch/BusinessInformation?businessId='
        . rawurlencode($entity_id) . '">View at the Virginia SCC</a>'
        . '</td></tr>';
}

$page_body .= detail_section('Registration', $rows, 'registration');

/*
 * Registered agent
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
    $page_body .= detail_section('Registered agent', $rows, 'agent');
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

        $rows .= "\n\t\t\t\t<tr><td>"
            . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            . '</td><td>'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</td></tr>';
    }

    if ($rows !== '')
    {
        $page_body .= "\n\t<section id=\"officers\">"
            . "\n\t\t<h2>Officers and directors</h2>"
            . "\n\t\t<table>"
            . "\n\t\t\t<caption class=\"visually-hidden\">Officers and directors</caption>"
            . "\n\t\t\t<thead>\n\t\t\t\t<tr><th scope=\"col\">Name</th>"
            . "<th scope=\"col\">Title</th></tr>\n\t\t\t</thead>"
            . "\n\t\t\t<tbody>" . $rows . "\n\t\t\t</tbody>"
            . "\n\t\t</table>\n\t</section>";
    }
}

/*
 * Stock, with assessment folded in: an assessment is a fact about the shares,
 * and giving it a section of its own meant partnerships got a whole heading to
 * carry a single value.
 */
$rows  = detail_row('Total shares', $shares);
$rows .= detail_row('Share classes', implode(', ', $stock));
$rows .= detail_row('Assessment', $business['AssessIndText'] ?? '');

/*
 * Entities without shares -- partnerships, trusts, authorities -- still carry an
 * assessment, so heading the section "Stock" would be wrong for them.
 */
$has_stock = trim((string) $shares) !== '' || !empty($stock);
$page_body .= detail_section($has_stock ? 'Stock' : 'Assessment', $rows, 'stock');

$template->assign('needs_map', strpos($page_body, 'id="map"') !== FALSE);
$template->assign('page_body', $page_body);
$template->assign('page_title', $page_title);
$template->assign('page_summary', $page_summary);
$template->assign('browser_title', $browser_title);

$template->display('includes/templates/simple.tpl');
