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
 * The filter and sort controls. These are normalised here so that the form
 * below can mark the current choice as selected, and validated again in
 * Business::search(), which is what actually builds the query.
 */
$status = strtoupper(trim($_GET['status'] ?? ''));
$sort   = strtolower(trim($_GET['sort'] ?? 'name'));

if (!in_array($status, Business::SEARCH_STATUSES, TRUE))
{
    $status = '';
}

if ($sort !== 'date')
{
    $sort = 'name';
}

/*
 * Newest first is the useful default for a date sort, alphabetical for a name
 * sort, so the direction follows the column unless the reader chose one.
 */
$order = strtolower(trim($_GET['order'] ?? ''));

if ($order !== 'asc' && $order !== 'desc')
{
    $order = ($sort === 'date') ? 'desc' : 'asc';
}

/*
 * Human-readable labels for the Status codes the SCC uses. PENDINACT is a
 * business behind on its filings but not yet terminated.
 */
$status_labels = array(
    'ACTIVE'    => 'Active',
    'INACTIVE'  => 'Inactive',
    'PENDINACT' => 'Pending inactive',
);

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
$template->assign('needs_statewide_map', FALSE);
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
$api_url = API_URL . '/api/search/' . rawurlencode($query)
    . '?' . http_build_query(array(
        'status' => $status,
        'sort'   => $sort,
        'order'  => $order,
    ));
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
    /*
     * A filter is the likeliest reason a search that would otherwise match
     * comes back empty, so offer a way out of it rather than leaving the
     * reader to edit the URL or start over.
     */
    $escape = '<p>Please try another search</p>';

    if ($status !== '')
    {
        $unfiltered = '/search/?' . htmlspecialchars(http_build_query(array(
            'q'     => $query,
            'sort'  => $sort,
            'order' => $order,
        )), ENT_QUOTES, 'UTF-8');

        $escape = '<p>No ' . strtolower($status_labels[$status])
            . ' businesses matched. <a href="' . $unfiltered
            . '">Search all statuses</a> instead.</p>';
    }

    $page_body = '
    <div class="row">
        <div class="card warning">
            <h3>No results found</h3>
            ' . $escape . '
        </div>
    </div>';
}
else
{

    $page_title = 'Search results';
    $page_summary = count($results) . ' result' . (count($results) === 1 ? '' : 's')
        . ' for &#8220;' . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . '&#8221;';

    if ($status !== '')
    {
        $page_summary .= ', ' . strtolower($status_labels[$status]) . ' only';
    }

    /*
     * A result set at the limit is a truncated one, and a reader sorting by
     * name has no way to tell that from the page. Say so rather than implying
     * these are all the matches.
     */
    if (count($results) >= Business::SEARCH_LIMIT)
    {
        $page_summary .= ' (showing the first ' . Business::SEARCH_LIMIT . ')';
    }

    /*
     * Clicking a column heading sorts by it; clicking the one already in use
     * reverses it. The link carries the query and filter along so that neither
     * is lost by sorting.
     */
    $sort_link = function ($column) use ($query, $status, $sort, $order)
    {

        $descend = ($sort === $column && $order === 'asc') ? 'desc' : 'asc';

        /*
         * A column the reader has not sorted by yet opens in its most useful
         * direction: A-Z for names, newest first for dates.
         */
        if ($sort !== $column)
        {
            $descend = ($column === 'date') ? 'desc' : 'asc';
        }

        return '/search/?' . htmlspecialchars(http_build_query(array(
            'q'      => $query,
            'status' => $status,
            'sort'   => $column,
            'order'  => $descend,
        )), ENT_QUOTES, 'UTF-8');

    };

    /*
     * The arrow marks the sorted column, and aria-sort tells a screen reader
     * the same thing the arrow tells everyone else.
     */
    $heading = function ($column, $label) use ($sort, $order, $sort_link)
    {

        $active = ($sort === $column);
        $arrow  = $active ? ($order === 'desc' ? ' &#9662;' : ' &#9652;') : '';
        $aria   = $active ? ($order === 'desc' ? 'descending' : 'ascending') : 'none';

        return '<th scope="col" aria-sort="' . $aria . '">'
            . '<a href="' . $sort_link($column) . '">' . $label . $arrow . '</a>'
            . '</th>';

    };

    /*
     * The filter submits as a GET form so that a filtered search is a URL the
     * reader can bookmark or share. The query and sort ride along as hidden
     * fields, so changing the filter does not reset them.
     */
    $page_body = '
    <form method="get" action="/search/" class="row" style="align-items: flex-end;">
        <input type="hidden" name="q" value="'
            . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . '">
        <input type="hidden" name="sort" value="'
            . htmlspecialchars($sort, ENT_QUOTES, 'UTF-8') . '">
        <input type="hidden" name="order" value="'
            . htmlspecialchars($order, ENT_QUOTES, 'UTF-8') . '">
        <div class="col-sm-8 col-md-4">
            <label for="status">Status</label>
            <select name="status" id="status">
                <option value="">All statuses</option>';

    foreach ($status_labels as $code => $label)
    {
        $page_body .= '<option value="' . $code . '"'
            . ($status === $code ? ' selected' : '') . '>'
            . $label . '</option>';
    }

    $page_body .= '
            </select>
        </div>
        <div class="col-sm-4 col-md-2">
            <button type="submit">Filter</button>
        </div>
    </form>

    <article>
        <table>
            <thead>
                <tr>
                    ' . $heading('name', 'Name') . '
                    ' . $heading('date', 'Inc. Date') . '
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
            