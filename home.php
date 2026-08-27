<?php

require 'includes/header.php';

$template = new Smarty;

$browser_title = 'Virginia Businesses';
$page_title = 'Virginia Businesses';
$page_body = '';

/*
* Query our API for recent businesses
*/
$api_url = API_URL . '/api/recent';

$recent_json = get_content($api_url);

/*
 * A failed request to our own API is logged rather than passed over in silence.
 * The homepage still renders without the listing -- the download table below is
 * worth serving on its own -- but the failure leaves a trace to find.
 */
if ($recent_json === false)
{
    error_log('Homepage listing failed: could not reach ' . $api_url);
}

$recent = json_decode($recent_json);
if (!empty($recent))
{
	
	$page_body .= '
		<article>
		<h2>Newest Businesses</h2>
		<ul class="listing">';

	foreach (array_slice($recent, 0, 9) as $business)
	{
		$page_body .= '
			<li>
				<a class="name" href="/business/' . rawurlencode($business->EntityID) . '">'
					. htmlspecialchars($business->Name, ENT_QUOTES, 'UTF-8') . '</a>
				<p class="meta">';

		if (!empty($business->City))
		{
			$page_body .= htmlspecialchars($business->City . ', ' . $business->State, ENT_QUOTES, 'UTF-8') . '<br>';
		}

		$page_body .= 'Incorporated ' . date('F j, Y', strtotime($business->IncorpDate)) . '</p>
			</li>';
	}

	$page_body .= '
		</ul>
		</article>';

}

/*
 * A pointer to the data, rather than the whole download table: /data/ lists the
 * files with descriptions, and repeating that here meant maintaining the same
 * list twice.
 */
require_once __DIR__ . '/includes/data-files.php';

$available = 0;
$total_bytes = 0;

foreach (data_files() as $filename => $file)
{
    $path = __DIR__ . '/data/' . $filename;

    if (is_readable($path))
    {
        $available++;
        $total_bytes += filesize($path);
    }
}

$page_body .= '
		<article>
		<h2>Download the data</h2>
		<p>Every record behind this site is available in bulk, as the files it was
		built from: corporations, LLCs, partnerships, business trusts and public
		service authorities, along with their officers, amendments, mergers and
		name histories.</p>';

if ($available > 0)
{
    $page_body .= '
		<p><a href="/data/">Browse ' . $available . ' data files</a>'
        . ' &mdash; ' . human_filesize_bytes($total_bytes) . ' in all.</p>';
}
else
{
    $page_body .= '
		<p><a href="/data/">Browse the data files</a>.</p>';
}

$page_body .= '
		</article>';

$template->assign('needs_map', FALSE);
$template->assign('page_body', $page_body);
$template->assign('page_title', $page_title);
$template->assign('page_summary', '');
$template->assign('browser_title', $browser_title);

$template->display('includes/templates/simple.tpl');
