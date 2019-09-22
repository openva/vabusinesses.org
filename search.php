<?php

include('vendor/autoload.php');

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

$template = new Smarty;

$page_title = 'Virginia Businesses';
$browser_title = 'Virginia Businesses';

/*
 * If no search query has been passed in the URL
 */
if (!isset($_GET['query']) || empty($_GET['query']))
{
    header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request", true, 400);
    exit();
}

$query = filter_var($_GET['query'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

/*
 * Query our own API 
 */
$api_url = 'https://vabusinesses.org/api/search/' . $query;
$results_json = get_content($api_url);

$results = json_decode($results_json);
if ($results === FALSE)
{
    header($_SERVER["SERVER_PROTOCOL"]." 500 Internal Server Error", true, 500);
    exit();
}

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


$template->assign('page_body', $page_body);
$template->assign('page_title', $page_title);
$template->assign('browser_title', $browser_title);

$template->display('includes/templates/simple.tpl');
            