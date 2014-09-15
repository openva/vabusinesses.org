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

		<script src="/js/vendor/modernizr-2.6.2-respond-1.1.0.min.js"></script>
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
<?php

/*
define('INCLUDE_PATH', $_SERVER['DOCUMENT_ROOT'] . 'includes/');

$tables = new TableMaps();
$tables->import_files();
*/

/////////////////////////////////
/*
 * Import all YAML table maps and turn them into PHP arrays. First, check Memcached.
 */
$mc = new Memcached();
$mc->addServer("127.0.0.1", 11211);
$tables = $mc->get('table-maps');
if ($tables !== FALSE)
{
	$tables = unserialize($tables);
}
else
{
	$dir = '../crump/table_maps/';
	$files = scandir($dir);
	foreach ($files as $file)
	{

		if ( ($file == '.') || ($file == '..') || ($file == '1_tables.yaml') )
		{
			continue;
		}
		$file_number = $file[0];
		$tables[$file_number] = yaml_parse_file($dir . $file);

	}

	/*
	 * Cache the table maps in Memcached, for 24 hours.
	 */
	$mc->set('table-maps', serialize($tables), 86400);
}

/*
 * Make the table data available as JSON.
 */
echo '<script>tables = \'' . json_encode($tables) . '\'</script>';

/*
 * Iterate through every field in every table map and use them to establish the proper sort order
 * for field names and a list of all valid field names (which we use for input sanitation).
 */
$sort_order = array();
$valid_fields = array();
foreach($tables as $table_number => $fields)
{

	foreach ($fields as $field)
	{

		$sort_order[$table_number][] = $field['alt_name'];

		/*
		 * Create a list of every valid field name.
		 */
		$valid_fields[] = $field['alt_name'];

	}

}
/////////////////////////////////

/*
 * Sanitize input.
 */
if (!empty($_GET['q']))
{
	$q = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_SPECIAL_CHARS);
	if (strlen($q) > 120)
	{
		die();
	}
}
if (!empty($_GET['p']))
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
if (!empty($_GET['sort_by']))
{
	$sort_by = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_SPECIAL_CHARS);
	if (strlen($sort_by) > 120)
	{
		unset($sort_by);
	}
	elseif (in_array($sort_by, $valid_fields) === FALSE)
	{
		unset($sort_by);
	}
}
if (!empty($_GET['order']))
{
	$order = filter_input(INPUT_GET, 'order', FILTER_SANITIZE_SPECIAL_CHARS);
	if (strlen($order) > 4)
	{
		die();
	}
}

/*
 * If this is requesting a particular type of data.
 */
if (!empty($_GET['type']))
{
	$type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_SPECIAL_CHARS);
	if ( (strlen($type) > 15) || preg_match('/[a-z]/', $type) !== 0 )
	{
		die();
	}
}

/*
 * If this is searching a particular field.
 */
if (!empty($_GET['field']))
{
	$search_field = filter_input(INPUT_GET, 'field', FILTER_SANITIZE_SPECIAL_CHARS);
	if (strlen($search_field) > 128)
	{
		die();
	}
}

/*
 * Create an instance of Elasticsearch.
 */
require 'vendor/autoload.php';
$client = new Elasticsearch\Client();

/*
 * Search the business index.
 */
$params['index'] = 'business';

/*
 * If a particular type of result has been requested.
 */
if (isset($type))
{
	$params['type'] = $type;
}

/*
 * These many results.
 */
$params['size'] = $per_page;

/*
 * If we have a query.
 */
if (isset($q))
{

	/*
	 * If this is a general search -- that is, not against a specific field.
	 */
	if (empty($search_field))
	{
		$params['body']['query']['match']['_all'] = $q;
	}
	
	/*
	 * If a specific field is being searched.
	 */
	else
	{
		$params['body']['query']['match'][$search_field] = $q;
	}
}

/*
 * Paging.
 */
if (isset($p))
{
	$params['from'] = ($p - 1) * $per_page;
}

/*
 * If we have a sort_by attribute.
 */
if (isset($sort_by))
{

	/*
	 * If we haven't specified a sort order.
	 */
	if (!isset($order))
	{
		$order = 'asc';
	}
	else
	{
		if (($order != 'asc') && ($order != 'desc'))
		{
			$order = 'asc';
		}
	}
	
	/*
	 * If $sort_by contains a valid value.
	 */
	$params['body']['sort'][$sort_by] = $order;
	
}

/*
 * Display the search form.
 */
echo '
<form method="get" action="/search.php">
	<input type="text" name="q" value="' . $q . '" />
	<select name="type">
		<option value=""></option>
		<option value="2,3,9"' . (($type == '2,3,9') ? ' selected' : '') . '>Businesses</option>
		<option value="6,8"' . (($type == '6,8') ? ' selected' : '') . '>Registered Names</option>
		<option value="4"' . (($type == '4') ? ' selected' : '') . '>Amendments</option>
		<option value="5"' . (($type == '5') ? ' selected' : '') . '>Officers</option>
		<option value="7"' . (($type == '7') ? ' selected' : '') . '>Mergers</option>
	</select>
	<input type="submit" value="Search" />
</form>';

/*
 * Execute the search.
 */
$results = $client->search($params);

if ( ($results === FALSE) || ($results['hits']['total'] == 0) )
{
	echo '<p>No results found.</p>';
}
else
{
	echo '<p>' . number_format($results['hits']['total']) . ' results found.</p>';
}

/*
 * If we have any results, display them.
 */
if (count($results['hits']['hits']) > 0)
{
	
	foreach ($results['hits']['hits'] as $result)
	{

		/*
		 * Rearrange the fields per the prescribed key order for this type of record.
		 */
		if (isset($sort_order[$result{'_type'}]))
		{
			$ordered_result = array();
			foreach ($sort_order[$result{'_type'}] as $key)
			{
				$ordered_result[$key] = $result['_source'][$key];	
			}
			
			/*
			 * Replace the result with the newly ordered result.
			 */
			$result['_source'] = $ordered_result;
			unset($ordered_result);
			
		}
		
		echo '<dl>';
		
		/*
		 * Iterate through every key/value pair contained within this result.
		 */
		foreach ($result['_source'] as $key => $value)
		{
			
			//if (!empty($value))
			//{
				
				foreach ($tables[$result{'_type'}] as $field)
				{
					if ($field['alt_name'] == $key)
					{
						$description = $field['description'];
						if (isset($field['group']))
						{
							$group = $field['group'];
						}
					}
				}
				
				$key = str_replace('_', ' ', $key);
				$key = str_replace('-', ' ', $key);
				$key = ucwords($key);
				if ($key == 'Id')
				{
					$key = 'ID';
				}
				
				/*
				 * Make IDs search links.
				 */
				if (strtolower($key) == 'id')
				{
					$value = '<a href="/search.php?q=' . urlencode($value) . '">' . $value . '</a>';
				}
				
				/*
				 * If this is a date field, format it.
				 */
				if ( (stripos($key, 'date') !== FALSE) && !empty($value) )
				{
					$value = date('M. j, Y', strtotime($value));
				}
				
				/*
				 * Display the field name (e.g., "Name," "Industry," etc.)
				 */
				echo '<dt';
				if (isset($description))
				{
					echo ' data-description="' . $description . ' "';
				}
				if (isset($group))
				{
					echo ' class="grouped ' . $group . '"';
				}
				echo '>' . $key . '</dt>';
				
				/*
				 * Display the value (e.g., the name of the business, the name of the RA, etc.)
				 */
				if (empty($value))
				{
					$value = '-';
				}
				echo '<dd>' . $value . '</dd>';
				
				/*
				 * Unset variables that we don't want to reuse on the next iteration.
				 */
				unset($group);
				unset($description);
				
			//}
			
		}
		echo '</dl>';
		
	}

}

/*
 * Display pagination.
 */
if ($results['hits']['total'] > (($p - 1) * $per_page) )
{
	
	$total_pages = ceil($results['hits']['total'] / $per_page);
	echo '<ul class="paging">';
	for ($i=1; $i<$total_pages; $i++)
	{
		if ($i != $p)
		{
			echo '<li><a href="/search.php?';
			if (isset($q))
			{
				echo 'q=' . urlencode($q) . '&amp;';
			}
			if (isset($sort_by))
			{
				echo 'sort_by=' . urlencode($sort_by) . '&amp;';
			}
			if (isset($order))
			{
				echo 'order=' . urlencode($order) . '&amp;';
			}
			if (isset($type))
			{
				echo 'type=' . urlencode($type) . '&amp;';
			}
			if (isset($search_field))
			{
				echo 'field=' . urlencode($search_field) . '&amp;';
			}
			echo 'p=' . $i . '">' . $i . '</a></li>';
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
			</div> <!-- #main -->
		</div> <!-- #main-container -->

		<div class="footer-container">
			<footer class="wrapper">
				<a href="https://www.shuttleworthfoundation.org/fellowship/fellows/grantees/"><img src="/img/shuttleworth.gif" width="150" height="43" alt="Shuttleworth Funded" id="shuttleworth" /></a>
				<a href="http://www.briworks.com/"><img src="/img/bri.gif" width="100" height="35" alt="Hosting Donated By Blue Ridge InternetWorks" id="bri" /></a>
				<p id="updated"><em>Data last updated on <?php echo date('F d, Y, g:i a', filectime('1_tables.csv') ); ?>.</em></p>
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
