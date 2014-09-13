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
			'name'	=> 'Inc. Registrations',
			'csv'	=> '2_corporate.csv',
			'json'	=> '2_corporate.json'),
		array(
			'name'	=> 'LP Registrations',
			'csv'	=> '3_lp.csv',
			'json'	=> '3_lp.json'),
		array(
			'name'	=> 'Inc./LP/LLC Amendments',
			'csv'	=> '4_amendments.csv',
			'json'	=> '4_amendments.json'),
		array(
			'name'	=> 'Corporate Officer',
			'csv'	=> '5_officers.csv',
			'json'	=> '5_officers.json'),
		array(
			'name'	=> 'Inc./LP/LLC Names',
			'csv'	=> '6_name.csv',
			'json'	=> '6_name.json'),
		array(
			'name'	=> 'Mergers',
			'csv'	=> '7_merger.csv',
			'json'	=> '7_merger.json'),
		array(
			'name'	=> 'Inc./LP/LLC Reserved/Registered Names',
			'csv'	=> '8_registered_names.csv',
			'json'	=> '8_registered_names.json'),
		array(
			'name'	=> 'LLC Registrations',
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
<!--[if lt IE 7]>	  <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>		 <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>		 <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js"> <!--<![endif]-->
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
		<title>Virginia Businesses</title>
		<meta name="description" content="">
		<meta name="viewport" content="width=device-width, initial-scale=1">

		<link rel="stylesheet" href="css/normalize.min.css">
		<link rel="stylesheet" href="css/main.css">
		<link rel="stylesheet" href="css/styles.css">

		<script src="js/vendor/modernizr-2.6.2-respond-1.1.0.min.js"></script>
	</head>
	
	<body id="page-home">

		<!--[if lt IE 7]>
			<p class="browsehappy">You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade your browser</a> to improve your experience.</p>
		<![endif]-->

		<div class="header-container">
			<header class="wrapper clearfix">
				<a href="https://github.com/openva/business.openva.com"><img style="position: absolute; top: 0; right: 0; border: 0;" src="https://camo.githubusercontent.com/365986a132ccd6a44c23a9169022c0b5c890c387/68747470733a2f2f73332e616d617a6f6e6177732e636f6d2f6769746875622f726962626f6e732f666f726b6d655f72696768745f7265645f6161303030302e706e67" alt="Fork me on GitHub" data-canonical-src="https://s3.amazonaws.com/github/ribbons/forkme_right_red_aa0000.png"></a>
				<h1 class="title">Virginia Businesses</h1>
				<!--<nav>
					<ul>
						<li><a href="#">nav ul li a</a></li>
						<li><a href="#">nav ul li a</a></li>
						<li><a href="#">nav ul li a</a></li>
					</ul>
				</nav>-->
			</header>
		</div>
		<div class="main-container">
			<div class="main wrapper clearfix">

				<p>Data <a href="https://www.scc.virginia.gov/clk/purch.aspx">purchased from the Virginia State
				Corporation Commission</a> and parsed with <a href="https://github.com/openva/crump/">Crump</a>.</p>

				<article>

				<form method="get" action="/search.php" id="search">
					<input type="text" name="q" />
					<input type="submit" value="Search" />
				</form>

				<ul>
					<li><a href="/search.php?sort_by=incorporation_date&amp;order=desc&amp;type=2%2C3%2C9">Newest Businesses</a></li>
					<li><a href="/search.php?sort_by=incorporation_date&amp;order=asc&amp;type=2%2C3%2C9">Oldest Businesses</a></li>
				</ul>

				<table>
					<caption>Bulk Data</caption>
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
				
				<aside>
					<h3>aside</h3>
					<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sodales urna non odio egestas tempor. Nunc vel vehicula ante. Etiam bibendum iaculis libero, eget molestie nisl pharetra in. In semper consequat est, eu porta velit mollis nec. Curabitur posuere enim eget turpis feugiat tempor. Etiam ullamcorper lorem dapibus velit suscipit ultrices.</p>
				</aside>

			</div> <!-- #main -->
		</div> <!-- #main-container -->

		<div class="footer-container">
			<footer class="wrapper">
				<a href="https://www.shuttleworthfoundation.org/fellowship/fellows/grantees/"><img src="/shuttleworth.gif" width="150" height="43" alt="Shuttleworth Funded" id="shuttleworth" /></a>
				<a href="http://www.briworks.com/"><img src="/bri.gif" width="100" height="35" alt="Hosting Donated By Blue Ridge InternetWorks" id="bri" /></a>
				<p id="updated"><em>Last updated on <?php echo date('F d, Y, g:i a', filectime('1_tables.csv') ); ?>.</em></p>
			</footer>
		</div>

		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
		<script>window.jQuery || document.write('<script src="js/vendor/jquery-1.11.0.min.js"><\/script>')</script>

		<script src="js/main.js"></script>

		<!-- Google Analytics: change UA-XXXXX-X to be your site's ID. -->
		<script>
			(function(b,o,i,l,e,r){b.GoogleAnalyticsObject=l;b[l]||(b[l]=
			function(){(b[l].q=b[l].q||[]).push(arguments)});b[l].l=+new Date;
			e=o.createElement(i);r=o.getElementsByTagName(i)[0];
			e.src='//www.google-analytics.com/analytics.js';
			r.parentNode.insertBefore(e,r)}(window,document,'script','ga'));
			ga('create','UA-76084-7');ga('send','pageview');
		</script>
	</body>
</html>

