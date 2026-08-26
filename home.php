<?php

require 'includes/header.php';

$template = new Smarty;

$browser_title = 'Virginia Businesses';
$page_title = '';
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
 * List the bulk data files for download. Sizes are read from disk rather than
 * hard-coded: the SCC data grows with every weekly update, and the previous
 * static figures had drifted badly (llc.csv was listed at 156 MB when it had
 * reached 437 MB). Files absent from a given build are simply not listed.
 */
$data_files = array(
	'corp.csv'          => 'Corporate Entities',
	'llc.csv'           => 'LLC Entities',
	'lp.csv'            => 'LP Entities',
	'gp.csv'            => 'General Partnership Entities',
	'bt.csv'            => 'Business Trust Entities',
	'psa.csv'           => 'Public Service Authority Entities',
	'amendment.csv'     => 'Entity Amendments',
	'merger.csv'        => 'Entity Mergers',
	'name_history.csv'  => 'Entity Name/Fictitious Name History',
	'officer.csv'       => 'Entity Officers/Directors',
	'reserved_name.csv' => 'Entity Reserved Names',
	'tables.csv'        => 'Descriptive Tables',
	'vabusinesses.sqlite' => 'All Data, SQLite',
);

$page_body .= '
		<article>

		<table>
			<caption>Download Business Data</caption>
			<thead>
				<tr>
					<th scope="col">File</th>
					<th scope="col" class="numeric">Size</th>
				</tr>
			</thead>
			<tbody>';

foreach ($data_files as $filename => $label)
{
	$path = __DIR__ . '/data/' . $filename;

	if (!is_readable($path))
	{
		continue;
	}

	$page_body .= '
				<tr>
					<td data-label="File"><a href="data/' . $filename . '">' . $label . '</a></td>
					<td data-label="Size" class="numeric">' . human_filesize($path) . '</td>
				</tr>';
}

$page_body .= '
				<tr>
					<td data-label="File"><a href="https://cis.scc.virginia.gov/DataSales/DownloadBEDataSalesFile">All Data, CSV (from the SCC)</a></td>
					<td data-label="Size">&#8212;</td>
				</tr>
			</tbody>
		</table>
		</article>';

$template->assign('needs_map', FALSE);
$template->assign('page_body', $page_body);
$template->assign('page_title', $page_title);
$template->assign('browser_title', $browser_title);

$template->display('includes/templates/simple.tpl');
