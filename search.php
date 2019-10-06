<?php

require 'header.php';

$template = new Smarty;

$browser_title = 'Virginia Businesses';

$query = filter_var($query, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

/*
 * If no search query has been passed in the URL
 */
if (!isset($query) || empty($query))
{
    header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request", true, 400);
    exit();
}

/*
 * Query our own API 
 */
$api_url = API_URL . '/api/search/' . $query;
$results_json = get_content($api_url);

$results = json_decode($results_json);
if ($results === FALSE)
{
    header($_SERVER["SERVER_PROTOCOL"]." 500 Internal Server Error", true, 500);
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
    $page_body .= '<tr>
        <td><a href="/business/' . $business->EntityID . '">' . $business->Name . '</a></td>
        <td>' . $business->IncorpDate . '</td>
        <td>' . $business->Status . '</td>
        </tr>';
    }

    $page_body .= '
                </tbody>
            </table>';

}

$template->assign('page_body', $page_body);
$template->assign('page_title', $page_title);
$template->assign('browser_title', $browser_title);

$template->display('includes/templates/simple.tpl');
            