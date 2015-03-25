<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
		<title>Business: Virginia Businesses</title>
		<meta name="description" content="">
		<meta name="viewport" content="width=device-width, initial-scale=1">

		<link rel="stylesheet" href="/css/normalize.min.css">
		<link rel="stylesheet" href="/css/main.css">
		<link rel="stylesheet" href="/css/styles.css">

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

<?php

if ( isset($_GET['id']) && strlen($_GET['id']) < 15 )
{

	$business = new Businesses();
	$business->id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_STRING);
	if ($business->get_record() == FALSE)
	{
		echo '<p>Error: There is no business with that ID.</p>';
	}

	echo '<h1>' . $business->record->name . '</h1>';

	echo '<table><tbody>';

	/*
	 * Iterate through every key/value pair, skipping nested arrays.
	 */
	foreach ($business->record as $key => $value)
	{


		if ( ($key == 'foreign') || ($key == 'domestic') )
		{
			if ($value === FALSE)
			{
				$value = 'no';
			}
			else
			{
				$value = 'yes';
			}
		}

		/*
		 * Skip blank fields.
		 */
		if ( $value == ''  || is_object($value)  || is_array($value) )
		{
			continue;
		}
		
		echo '<tr><td>' . $key . '</td><td>' . $value . '</td></tr>';

	}

	/*
	 * If we have a list of agent records.
	 */
	if (count($business->record->agent) > 0)
	{

		echo '<tr><td colspan="2"><strong>Agent</strong></td></tr>
			<tr><td colspan="2">
			<table>';

		foreach ($business->record->agent as $key => $value)
		{
			echo '<tr><td>' . $key . '</td><td>' . $value . '</td></tr>';
		}

		echo '</table></td></tr>';

	}

	/*
	 * If we have a list of amendments.
	 */
	if (count($business->record->amendments) > 0)
	{

		echo '<tr><td colspan="2"><strong>Amendments</strong></td></tr>
			<tr><td colspan="2">
			<table>';

		foreach ($business->record->amendments as $amendment)
		{

			echo '<tr><td colspan="2"><strong>Amendment</strong></td></tr>';

			foreach ($amendment as $key => $value)
			{

				echo '<tr><td>' . $key . '</td><td>' . $value . '</td></tr>';

			}

		}

		echo '</table></td></tr>';

	}

	/*
	 * If we have a list of mergers.
	 */
	if (count($business->record->mergers) > 0)
	{

		echo '<tr><td colspan="2"><strong>Mergers</strong></td></tr>
			<tr><td colspan="2">
			<table>';

		foreach ($business->record->mergers as $merger)
		{

			echo '<tr><td colspan="2"><strong>Merger</strong></td></tr>';

			foreach ($merger as $key => $value)
			{

				echo '<tr><td>' . $key . '</td><td>' . $value . '</td></tr>';

			}

		}

		echo '</table></td></tr>';

	}

	/*
	 * If we have a list of officers.
	 */
	if (count($business->record->officers) > 0)
	{

		echo '<tr><td colspan="2"><strong>Officer</strong></td></tr>
			<tr><td colspan="2">
			<table>';

		foreach ($business->record->officers as $officer)
		{

			echo '<tr><td colspan="2"><strong>Officer</strong></td></tr>';

			foreach ($officer as $key => $value)
			{

				echo '<tr><td>' . $key . '</td><td>' . $value . '</td></tr>';

			}

		}

		echo '</table></td></tr>';

	}

	echo '</tbody></table>';


}

?>

				</article>

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

		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
		<script>window.jQuery || document.write('<script src="js/vendor/jquery-1.11.0.min.js"><\/script>')</script>

		<script src="js/main.js"></script>

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
