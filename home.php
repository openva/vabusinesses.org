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
 * A preview of the statewide map, linking to the full one. The preview is not
 * interactive -- panning and zooming are off -- so it reads as a picture of
 * where Virginia's businesses are rather than as a map to be explored here.
 */
$map_summary_file = __DIR__ . '/data/map/summary.json';
$map_summary = is_readable($map_summary_file)
    ? json_decode(file_get_contents($map_summary_file), TRUE)
    : NULL;

$show_map = MAP_TILES !== '' && is_array($map_summary);

if ($show_map)
{
    $page_body .= '
		<article>
		<h2>Where Virginia does business</h2>
		<a class="map-preview-link" href="/map/">
			<div id="statewide-map" data-preview
				data-tiles="' . htmlspecialchars(MAP_TILES, ENT_QUOTES, 'UTF-8') . '"
				data-attribution="' . htmlspecialchars(MAP_ATTRIBUTION, ENT_QUOTES, 'UTF-8') . '"></div>
		</a>
		<p>' . number_format($map_summary['total']) . ' registered businesses, mapped
		across Virginia&rsquo;s counties and cities.
		<a href="/map/">Explore the map</a>.</p>
		</article>';
}

/*
 * A pointer to the bulk data. The full list, with descriptions, is on /data/.
 */
require_once __DIR__ . '/includes/data-files.php';

$available = 0;

foreach (data_files() as $filename => $file)
{
    if (is_readable(__DIR__ . '/data/' . $filename))
    {
        $available++;
    }
}

$page_body .= '
		<article>
		<h2>Download the data</h2>
		<p>Every record behind this site is available in bulk, as the files it was
		built from. <a href="/data/">Browse ' . $available . ' data files</a>.</p>
		</article>';

$template->assign('needs_map', $show_map);
$template->assign('needs_statewide_map', $show_map);
$template->assign('page_body', $page_body);
$template->assign('page_title', $page_title);
$template->assign('page_summary', '');
$template->assign('browser_title', $browser_title);

$template->display('includes/templates/simple.tpl');
