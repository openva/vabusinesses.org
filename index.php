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
$page_body = '
		<article>

			<form method="get" action="/search.php">
				<label for="query">Search</label>
				<input type="text" size="50" name="query" id="query">
				<input type="submit" value="Go">
			</form>

		</article>';



		/*
		* Query our API for recent businesses
		*/
		if (!empty($SERVER['HTTPS']))
		{
			$api_url = 'https';
		}
		else {
			$api_url = 'http';
		}
		$api_url .= '://';
		$api_url .= $_SERVER['SERVER_NAME'];
		$api_url .= '/api/recent';

		$recent_json = get_content($api_url);
		$recent = json_decode($recent_json);
		if ($recent != FALSE)
		{

			$page_body .= '
			<article>
				<h2>Newest Corporations</h2>
				<ul>';

			foreach ($recent as $business)
			{
				$page_body .= '<li><a href="/business/' . $business->EntityID . '/">' . $business->Name . '</a></li>';
			}

			$page_body .= '</ul></article>';
		
		}

$page_body .= '
		<article>

		<table>
			<caption>Download Business Data</caption>
			<thead>
				<tr>
					<th scope="col">File</th>
					<th scope="col">Size</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><a href="data/amendment.csv">Entity Amendments</a></td>
					<td>6 MB</td>
				</tr>
				<tr>
					<td><a href="data/corp.csv">Corporate Entities</a></td>
					<td>87 MB</td>
				</tr>
				<tr>
					<td><a href="data/llc.csv">LLC Entities</a></td>
					<td>156 MB</td>
				</tr>
				<tr>
					<td><a href="data/lp.csv">LP Entities</a></td>
					<td>3 MB</td>
				</tr>
				<tr>
					<td><a href="data/merger.csv">Entity Mergers</a></td>
					<td>3 MB</td>
				</tr>
				<tr>
					<td><a href="data/name.history.csv">Entity Name/Fictitious Name History</a></td>
					<td>16 MB</td>
				</tr>
				<tr>
					<td><a href="data/officer.csv">Entity Officers/Directors</a></td>
					<td>29 MB</td>
				</tr>
				<tr>
					<td><a href="data/reserved.name.csv">Entity Reserved Names</a></td>
					<td>0.1 MB</td>
				</tr>
				<tr>
					<td><a href="data/tables.csv">Descriptive Tables</a></td>
					<td>0.1 MB</td>
				</tr>
				<tr>
					<td><a href="http://scc.virginia.gov/clk/data/CISbemon.CSV.zip">All Data, CSV</a></td>
					<td>77 MB</td>
				</tr>
				<tr>
					<td><a href="data/vabusinesses.sqlite">All Data, SQLite</a></td>
					<td>321 MB</td>
				</tr>
			</tbody>
		</table>
		</article>';

$template->assign('page_body', $page_body);
$template->assign('page_title', $page_title);
$template->assign('browser_title', $browser_title);

$template->display('includes/templates/simple.tpl');
