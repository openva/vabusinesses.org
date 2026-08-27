<?php

require 'includes/header.php';

$template = new Smarty;

$browser_title = 'Virginia Businesses';
$page_title = 'Search';

/*
 * Held as the user typed it. Escaping here would corrupt the search itself --
 * "Book N' Scoop" would be looked up as "Book N&#039; Scoop" -- so the value is
 * escaped at each point it is rendered instead, below.
 */
$query = trim($_GET['q'] ?? '');

/*
 * With no query, show the search form rather than an error. This page is linked
 * from the site navigation, so arriving here without a term is the normal way to
 * begin a search, not a bad request.
 */
if ($query === '')
{
    $page_body = '
    <p>Search the records of every business registered with the Virginia State
    Corporation Commission—corporations, LLCs, partnerships, business
    trusts and public service authorities.</p>';

    $template->assign('needs_map', FALSE);
    $template->assign('page_body', $page_body);
    $template->assign('page_title', $page_title);
    $template->assign('page_summary', '');
    $template->assign('browser_title', $browser_title);
    $template->display('includes/templates/simple.tpl');

    exit();
}

/*
 * Query our own API 
 */
$api_url = API_URL . '/api/search/' . rawurlencode($query);
$results_json = get_content($api_url);

/*
 * get_content() returns FALSE when the request to our own API failed outright.
 * That is a server fault, not an empty result set: reporting it as "no results
 * found" makes a broken API indistinguishable from a search that matched
 * nothing, which is exactly how a site-wide search outage goes unnoticed.
 */
if ($results_json === false)
{
    error_log('Search failed: could not reach ' . $api_url);
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    exit();
}

$results = json_decode($results_json);

if ($results === null)
{
    error_log('Search failed: ' . $api_url . ' did not return valid JSON');
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    exit();
}

if ( !is_array($results) || count($results) == 0 )
{
    $page_body = '
    <div class="row">
        <div class="card warning">
            <h3>No results found</h3>
            <p>Please try another search</p>
        </div>
    </div>';
}
else
{

    $page_title = 'Search results';
    $page_summary = count($results) . ' result' . (count($results) === 1 ? '' : 's')
        . ' for &#8220;' . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . '&#8221;';

    $page_body = '
    <article>
        <table>
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Inc. Date</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>';

    /*
    * Display a table of all results values
    */
    foreach ($results as $business)
    {
        $incorporated = trim($business->IncorpDate ?? '');
        if ($incorporated !== '')
        {
            $incorporated = date('M j, Y', strtotime($incorporated));
        }

        $page_body .= '<tr>
        <td><a href="/business/' . rawurlencode($business->EntityID) . '">'
            . htmlspecialchars($business->Name, ENT_QUOTES, 'UTF-8') . '</a></td>
        <td>' . htmlspecialchars($incorporated, ENT_QUOTES, 'UTF-8') . '</td>
        <td>' . htmlspecialchars(trim($business->Status ?? ''), ENT_QUOTES, 'UTF-8') . '</td>
        </tr>';
    }

    $page_body .= '
                </tbody>
            </table>';

}

$template->assign('needs_map', FALSE);
$template->assign('page_body', $page_body);
$template->assign('page_title', $page_title);
$template->assign('page_summary', $page_summary ?? '');
$template->assign('browser_title', $browser_title);

$template->display('includes/templates/simple.tpl');
            