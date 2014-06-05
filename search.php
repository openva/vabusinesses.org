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
		.paging {
			list-style-type: none;
		}
			.paging li {
				display: inline;
				padding-right: 1em;
			}
		dl {
			border: 3px double #ccc;
			padding: 0.5em;
		}
		dt {
			float: left;
			clear: left;
			width: 100px;
			text-align: right;
			font-weight: bold;
			color: green;
		}
		dt:after {
			content: ":";
		}
		dd {
			margin: 0 0 0 110px;
			padding: 0 0 0.5em 0;
		}
	</style>
</head>
<body>

<header>
<a href="https://github.com/openva/business.openva.com"><img style="position: absolute; top: 0; right: 0; border: 0;" src="https://camo.githubusercontent.com/365986a132ccd6a44c23a9169022c0b5c890c387/68747470733a2f2f73332e616d617a6f6e6177732e636f6d2f6769746875622f726962626f6e732f666f726b6d655f72696768745f7265645f6161303030302e706e67" alt="Fork me on GitHub" data-canonical-src="https://s3.amazonaws.com/github/ribbons/forkme_right_red_aa0000.png"></a>

<h1>Virginia Businesses</h1>
<article>
<?php

/*
 * Sanitize input.
 */
if (isset($_GET['q']))
{
	$q = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_SPECIAL_CHARS);
	if (strlen($q) > 120)
	{
		die();
	}
}
if (isset($_GET['p']))
{
	$p = filter_input(INPUT_GET, 'p', FILTER_SANITIZE_SPECIAL_CHARS);
	if ( (strlen($p) > 4) || !is_numeric($p) )
	{
		die();
	}
}
else
{
	$p = 1;
}
$per_page = 10;

/*
 * Create an instance of Elasticsearch.
 */
require 'vendor/autoload.php';
$client = new Elasticsearch\Client();

/*
 * Search the business index.
 */
$params['index'] = 'business';

$params['size'] = $per_page;

if (isset($q))
{
	$params['body']['query']['match']['_all'] = $q;
}
if (isset($p))
{
	$params['from'] = $p * $per_page;
}

echo '
<form method="get" action="/search.php">
	<input type="text" name="q" value="' . $q . '" />
	<input type="submit" value="Search" />
</form>';

$results = $client->search($params);

echo '<p>' . number_format($results['hits']['total']) . ' results found.</p>';

/*
 * If we have any results, display them.
 */
if ($results['hits']['total'] > 0)
{

	foreach ($results['hits']['hits'] as $result)
	{
		echo '<dl>';
		foreach ($result['_source'] as $key => $value)
		{
			if (!empty($value))
			{
				$tmp = explode('-', $key);
				unset($tmp[0]);
				$key = implode(' ', $tmp);
				$key = ucwords($key);
				echo '<dt>' . $key . '</dt><dd>' . $value . '</dd>';
			}
		}
		echo '</dl>';
	}

}

/*
 * Display pagination.
 */
if ($results['hits']['total'] > ($p * $per_page) )
{
	
	$total_pages = ceil($results['hits']['total'] / $per_page);
	echo '<ul class="paging">';
	for ($i=1; $i<=$total_pages; $i++)
	{
		if ($i != $p)
		{
			echo '<li><a href="/search.php?q=' . $q . '&amp;p=' . $i . '">' . $i . '</a></li>';
		}
		else
		{
			echo '<li>' . $i . '</li>';
		}
		if ($i == 20)
		{
			break;
		}
	}
	echo '</ul>';
	
}

?>
</article>

<footer>
<p id="updated"><em>Last updated on <?php echo date('F d, Y, g:i a', filectime('1_tables.csv') ); ?>.</em></p>
</footer>

</body>
</html>
