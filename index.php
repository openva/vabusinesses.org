<?php

include('includes/header.php');

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
		$api_url .= API_URL . '/api/recent';

		$recent_json = get_content($api_url);
		$recent = json_decode($recent_json);
		if ($recent != FALSE)
		{
			
			$page_body .= '
				<article class="container">
				<h2>Newest Businesses</h2>';

			$i=3;
			if (count($recent) > 9)
			{
				$recent = array_slice($recent, 0, 9);
			}
			foreach ($recent as $business)
			{

				if ( ($i % 3) == 0 )
				{
					$page_body .= '<div class="row">';
				}
				
				$page_body .= '
					<div class="card small">
						<h3><a href="/business/' . $business->EntityID . '">' . $business->Name . '</a></h3>
						<p>';
				if (!empty($business->City))
				{
					$page_body .= $business->City . ', ' . $business->State . '<br>';
				} 
				$page_body .= date('M d, Y', strtotime($business->IncorpDate)) . '</p>
					</div>';

				if ( ($i % 3) == 2 )
				{
					$page_body .= '</div>';
				}
				$i++;

			}

			$page_body .= '</article>';
		
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
					<td data-label="File"><a href="data/amendment.csv">Entity Amendments</a></td>
					<td data-label="Size">6 MB</td>
				</tr>
				<tr>
					<td data-label="File"><a href="data/corp.csv">Corporate Entities</a></td>
					<td data-label="Size">87 MB</td>
				</tr>
				<tr>
					<td data-label="File"><a href="data/llc.csv">LLC Entities</a></td>
					<td data-label="Size">156 MB</td>
				</tr>
				<tr>
					<td data-label="File"><a href="data/lp.csv">LP Entities</a></td>
					<td data-label="Size">3 MB</td>
				</tr>
				<tr>
					<td data-label="File"><a href="data/merger.csv">Entity Mergers</a></td>
					<td data-label="Size">3 MB</td>
				</tr>
				<tr>
					<td data-label="File"><a href="data/name.history.csv">Entity Name/Fictitious Name History</a></td>
					<td data-label="Size">16 MB</td>
				</tr>
				<tr>
					<td data-label="File"><a href="data/officer.csv">Entity Officers/Directors</a></td>
					<td data-label="Size">29 MB</td>
				</tr>
				<tr>
					<td data-label="File"><a href="data/reserved.name.csv">Entity Reserved Names</a></td>
					<td data-label="Size">0.1 MB</td>
				</tr>
				<tr>
					<td data-label="File"><a href="data/tables.csv">Descriptive Tables</a></td>
					<td data-label="Size">0.1 MB</td>
				</tr>
				<tr>
					<td data-label="File"><a href="http://scc.virginia.gov/clk/data/CISbemon.CSV.zip">All Data, CSV</a></td>
					<td data-label="Size">77 MB</td>
				</tr>
				<tr>
					<td data-label="File"><a href="data/vabusinesses.sqlite">All Data, SQLite</a></td>
					<td data-label="Size">321 MB</td>
				</tr>
			</tbody>
		</table>
		</article>';

$template->assign('page_body', $page_body);
$template->assign('page_title', $page_title);
$template->assign('browser_title', $browser_title);

$template->display('includes/templates/simple.tpl');
