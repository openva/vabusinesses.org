<?php

include('vendor/autoload.php');

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

		</article>

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
