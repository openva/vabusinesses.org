<?php

function human_filesize($bytes, $decimals = 0)
{
	$sz = 'BKMGTP';
	$factor = floor((strlen($bytes) - 1) / 3);
	return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
}

class Businesses
{

	/*
	 * Declare the list of files.
	 */
	public $files = array(
		array(
			'name'	=> 'Corporate',
			'csv'	=> '2_corporate.csv',
			'json'	=> '2_corporate.json'),
		array(
			'name'	=> 'Limited Partnership',
			'csv'	=> '3_lp.csv',
			'json'	=> '3_lp.json'),
		array(
			'name'	=> 'Corporate/Limited Partnership/Limited Liability Company',
			'csv'	=> '4_amendments.csv',
			'json'	=> '4_amendments.json'),
		array(
			'name'	=> 'Corporate Officer',
			'csv'	=> '5_officers.csv',
			'json'	=> '5_officers.json'),
		array(
			'name'	=> 'Corporate/Limited Partnership/Limited Liability Company Name',
			'csv'	=> '6_name.csv',
			'json'	=> '6_name.json'),
		array(
			'name'	=> 'Merger',
			'csv'	=> '7_merger.csv',
			'json'	=> '7_merger.json'),
		array(
			'name'	=> 'Corporate/Limited Partnership/Limited Liability Company Reserved/Registered Name',
			'csv'	=> '8_registered_names.csv',
			'json'	=> '8_registered_names.json'),
		array(
			'name'	=> 'Limited Liability Company',
			'csv'	=> '9_llc.csv',
			'json'	=> '9_llc.json')
	);
	
	/*
	 * List the names, dates, and size of all CSV and JSON files.
	 */
	function list_files()
	{
		
		/*
		 * Iterate through all of the files to get their creation dates and sizes.
		 */
		foreach ($this->files as &$file)
		{
			$file['csv_size'] = filesize($file['csv']);
			$file['csv_date'] = filectime($file['csv']);
			$file['json_size'] = filesize($file['json']);
			$file['json_date'] = filectime($file['json']);
		}
		
		return TRUE;
		
	}

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Virginia Businesses</title>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
	<script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
	<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootswatch/3.1.1/united/bootstrap.min.css">
	<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap-theme.min.css">
	<style>
		body {
			margin: 0 1em;
		}
		#updated {
			margin-top: 1em;
			font-size: .85em;
		}
		thead {
			background-color: #999;
			color: #eee;
		}
		td, th {
			padding: .5em 0 .5em 1em;
		}
		tr:nth-child(even) {
			background-color: #eee;
		}
		#master-file {
			border-top: 1px solid #666;
		}
		#search {
			text-align: right;
			margin: 0 2em .5em 0;
		}
		#shuttleworth {
			float: right;
		}
	</style>
</head>
<body>

<header>
<a href="https://github.com/openva/business.openva.com"><img style="position: absolute; top: 0; right: 0; border: 0;" src="https://camo.githubusercontent.com/365986a132ccd6a44c23a9169022c0b5c890c387/68747470733a2f2f73332e616d617a6f6e6177732e636f6d2f6769746875622f726962626f6e732f666f726b6d655f72696768745f7265645f6161303030302e706e67" alt="Fork me on GitHub" data-canonical-src="https://s3.amazonaws.com/github/ribbons/forkme_right_red_aa0000.png"></a>

<h1>Virginia Businesses</h1>

<p>Data <a href="https://www.scc.virginia.gov/clk/purch.aspx">purchased from the Virginia State
Corporation Commission</a> and parsed with <a href="https://github.com/openva/crump/">Crump</a>.</p>
</head>

<article>

<form method="get" action="/search.php" id="search">
	<input type="text" name="q" />
	<input type="submit" value="Search" />
</form>

<table>
	<thead>
		<tr>
			<th>File</th>
			<th colspan="2">Download</th>
		</tr>
	</thead>
	<tbody>
		<?php
	
		$businesses = new Businesses;
		if ($businesses->list_files() === TRUE)
		{
			
			foreach ($businesses->files as $file)
			{
		
				echo '<tr>
				<td>' . $file['name'] . '</td>
				<td><a href="' . $file['csv']  . '">CSV</a> (' . human_filesize($file['csv_size']) . ')</td>
				<td><a href="' . $file['json']  . '">JSON</a> (' . human_filesize($file['json_size']) . ')</td>
				</tr>';
			
			}
		
		}
	
		?>
		<tr id="master-file">
			<td>SCC Master File (Contains All Data)</td>
			<td colspan="2"><a href="https://s3.amazonaws.com/virginia-business/current.zip">Fixed-Width</a></td>
		</tr>
	</tbody>
</table>
</article>

<footer>
<p id="updated"><em>Last updated on <?php echo date('F d, Y, g:i a', filectime('1_tables.csv') ); ?>.</em></p>
<a href="https://www.shuttleworthfoundation.org/fellowship/fellows/grantees/"><img src="/shuttleworth.gif" width="250" height="71" alt="Shuttleworth Funded" id="shuttleworth" /></a>
</footer>

</body>
</html>
