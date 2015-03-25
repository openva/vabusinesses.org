<?php

require $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

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
		<title>Virginia Businesses: Search</title>
		<meta name="description" content="">
		<meta name="viewport" content="width=device-width, initial-scale=1">

		<link rel="stylesheet" href="/css/normalize.min.css">
		<link rel="stylesheet" href="/css/main.css">
		<link rel="stylesheet" href="/css/styles.css">
		
		<!-- Leaflet -->
		<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.3/leaflet.css" />
		<script src="//cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.3/leaflet.js"></script>
		<style>
			#map {
				height: 200px;
			}
		</style>

		<script src="/js/vendor/modernizr-2.6.2-respond-1.1.0.min.js"></script>
	</head>
	<body id="page-home">

		<!--[if lt IE 7]>
			<p class="browsehappy">You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade your browser</a> to improve your experience.</p>
		<![endif]-->

		<div class="header-container">
			<header class="wrapper clearfix">
				<h1 class="title">Virginia Businesses</h1>
				<nav>
					<ul>
						<li><a href="/">Home</a></li>
						<li><a href="/data/">Data</a></li>
						<li><a href="/localities/">Localities</a></li>
					</ul>
				</nav>
			</header>
		</div>
		<div class="main-container">
			<div class="main wrapper clearfix">

				<article>

					<h1>Download Data for Localities</h1>

					<table style="width: 100%">
					<thead>
						<tr>
							<th>Name</th>
							<th colspan="2">Download As</th>
						</tr>
					</thead>
					<tbody>
<?php

$places = import_place_names();
foreach ($places as $id => $name)
{
	echo '<tr><td>' . $name . '</td><td><a href="/search/?q=&amp;type=2%2C3%2C9&amp;place=' . $id
		. '&amp;download=json">JSON</a></td><td><a href="/search/?q=&amp;type=2%2C3%2C9&amp;place='
		. $id . '&amp;download=csv">CSV</a></td></tr>';
}

?>
					</tbody>
					</table>
				</article>

				<aside>
					
					<p>These are lists of all businesses registered with the State Corporation
					Commission in each locality (city, county, and town) in Virginia. That includes
					businesses that exist now and businesses that no longer exist—as far back as
					the SCC’s records go. All records indicate whether they’re for businesses that
					still exist ("active" businesses) or otherwise.</p>
					
					<p>Select from the list the locality for which you want data from the list. If
					you don’t know the difference between JSON and CSV, then you want CSV (which
					can be opened in any spreadsheet software, e.g. Excel).</p>
					
					<p>If you’re a government employee looking to use this data, but you don’t know
					how to do it, <a href="mailto:waldo@jaquith.org">e-mail me</a> and I’ll help.</p>
					
				</aside>

			</div> <!-- #main -->
		</div> <!-- #main-container -->

		<div class="footer-container">
			<footer class="wrapper">
				Data last updated on <?php echo date('F d, Y, g:i a', filectime('data/1_tables.csv') ); ?>.
				All business records are created by the Virginia State Corporation Commission, and
				are thus without copyright protection, so may be reused and reproduced freely,
				without seeking any form of permission from anybody. All other website content is
				published under <a href="http://opensource.org/licenses/MIT">the MIT license</a>.
				This website is an independent, private effort, created and run as a hobby, and is
				in no way affiliated with the Commonwealth of Virginia or the State Corporation
				Commission. <a href="https://github.com/openva/vabusinesses.org">All site source
				code is on GitHub</a>—pull requests welcome.
			</footer>
		</div>

		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
		<script>window.jQuery || document.write('<script src="js/vendor/jquery-1.11.0.min.js"><\/script>')</script>
		<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.11.1/jquery-ui.min.js"></script>

		<script src="/js/main.js"></script>

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
