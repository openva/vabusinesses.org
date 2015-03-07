<?php

if ($_GET['debug'] == 'y')
{
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
}

require $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

/*
 * We use output buffering because we export JSON on this page, if the parameter is present. There's
 * no way to echo JSON (meaningfully) without clearing the buffer first.
 */
ob_start();

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
<?php

/*
 * We've already set both of these values in bootstrap.php.
 */
global $tables;
global $valid_fields;


/*
 * Make the table data available as JSON.
 */
echo '<script>tables = \'' . json_encode($tables) . '\'</script>';

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
if (!empty($_GET['per_page']))
{
	$per_page = filter_input(INPUT_GET, 'per_page', FILTER_SANITIZE_SPECIAL_CHARS);
	if ( (strlen($per_page) > 5) || !is_numeric($per_page) )
	{
		die();
	}
}
else
{
	$per_page = 10;
}
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
 * If this is limiting the search to a particular place.
 */
if (!empty($_GET['place']))
{
	$gnis_id = filter_input(INPUT_GET, 'place', FILTER_SANITIZE_SPECIAL_CHARS);
	if ( strlen($gnis_id) > 7 || !is_numeric($gnis_id) )
	{
		die();
	}
	
	/*
	 * GNIS IDs are left-padded with zeros to make them at least 3 digits.
	 */
	$gnis_id = str_pad($gnis_id, 3, '0', STR_PAD_LEFT);
	
	if (strlen($gnis_id) < 5)
	{
		$gnis_type = 'municipalities';
	}
	else
	{
		$gnis_type = 'towns';
	}
}

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
 * In this place.
 */
if (isset($gnis_id))
{

	$filter = array();
	$filter['geo_shape'] = array();
	$filter['geo_shape']['location'] = array();
	$filter['geo_shape']['location']['indexed_shape'] =
		array(
			'id' => $gnis_id,
			'type' => $gnis_type,
			'index' => 'shapes',
			'path' => 'geometry'
		);
				
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
		$params['body']['query']['match']['_all']['query'] = $q;
		$params['body']['query']['match']['_all']['operator'] = 'and';
	}
	
	/*
	 * If a specific field is being searched.
	 */
	else
	{
		$params['body']['query']['match'][$search_field]['query'] = $q;
		$params['body']['query']['match'][$search_field]['operator'] = 'and';
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
<form method="get" action="/search/">
	<input type="text" name="q" value="' . $q . '" />
	<select name="type">
		<option value="">Type</option>
		<option value="2,3,9"' . (($type == '2,3,9') ? ' selected' : '') . '>Businesses</option>
		<option value="6,8"' . (($type == '6,8') ? ' selected' : '') . '>Registered Names</option>
		<option value="4"' . (($type == '4') ? ' selected' : '') . '>Amendments</option>
		<option value="5"' . (($type == '5') ? ' selected' : '') . '>Officers</option>
		<option value="7"' . (($type == '7') ? ' selected' : '') . '>Mergers</option>
	</select>
	<select name="place">
		<option value="" disabled' . (empty($gnis_id) ? ' selected' : '') . '>Place</option>';
$places = import_place_names();
foreach ($places as $id => $name)
{
	echo '<option value="' . $id . '"' . (($gnis_id == $id) ? ' selected' : '') . '>' . $name . '</option>';
}
echo '
	</select>
	<input type="submit" value="Search" />
</form>';

/*
 * If we have a filter, apply it to the parameters.
 */
if (isset($filter))
{
	$params['body']['query']['filtered'] = array(
		'filter' => $filter);
	if (isset($params['body']['query']['match']))
	{
		$params['body']['query']['filtered']['query'] = $params['body']['query']['match'];
	}
	unset($params['body']['query']['match']);
}

//////////////////////////////
// This is not the right place to put this or way to do this.
//////////////////////////////
if (isset($_GET['download']))
{
	if ( ($_GET['download'] != 'csv') && ($_GET['download'] != 'json') )
	{
		$_GET['download'] = 'json';
	}
	ob_end_clean();
	$businesses = new Businesses;
	$businesses->format = $_GET['download'];
	$businesses->params = $params;
	$businesses->export_results();
	exit();
	
}
//////////////////////////////

/*
 * Execute the search.
 */
$results = $es->search($params);

if ( ($results === FALSE) || ($results['hits']['total'] == 0) )
{
	echo '<p>No results found.</p>';
}
else
{
	echo '<p>' . number_format($results['hits']['total']) . ' results found.';
	echo ' Download results: <a href="' . $_SERVER['REQUEST_URI'] . '&amp;download=json">JSON</a>,
			<a href="' . $_SERVER['REQUEST_URI'] . '&amp;download=csv">CSV</a>';
	echo '</p>';
}

/*
 * If we have any results, display them.
 */
if (count($results['hits']['hits']) > 0)
{
	echo '<p>' . number_format($results['hits']['total']) . ' results found.
		Download results: <a href="' . $_SERVER['REQUEST_URI'] . '&amp;download=json">JSON</a>,
			<a href="' . $_SERVER['REQUEST_URI'] . '&amp;download=csv">CSV</a></p>
		<div id="map"></div>
		<script>
			var map = L.map(\'map\').setView([37.99920, -79.46565], 6);
			L.tileLayer(\'https://{s}.tiles.mapbox.com/v3/waldoj.k2cmnij6/{z}/{x}/{y}.png\', {
				attribution: \'Map data &copy; <a href=http://openstreetmap.org>OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="http://mapbox.com">Mapbox</a>\',
				maxZoom: 18
			}).addTo(map);
			var resultLatLngs = []
		</script>';
	
	foreach ($results['hits']['hits'] as $result)
	{
		
		/*
		 * The SCC has records that erroneously list an incorporation date in the distant future.
 		 * These permanently top the list of recent incorporations. Solution: don't show any records
 		 * that claim future incorporation dates.
		 */
		if (strtotime($result['_source']['incorporation_date']) > time())
		{
			continue;
		}
		
		/*
		 * Raise coordinates up a level in the array structure.
		 */
		if (isset($result['_source']['location']['coordinates']))
		{
			$result['_source']['coordinates'] = $result['_source']['location']['coordinates'];
			unset($result['_source']['location']);
		}
		
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
		
		if (!empty($result['_source']['coordinates']))
		{
			$id = $result['_source']['id'];
			echo '
			<script>
				var marker_' . $id . ' = L.marker([' . $result['_source']['coordinates'][1] . ','
					. $result['_source']['coordinates'][0] . ']).addTo(map);
				marker_' . $id . '.bindPopup("<a href=\"/search/?q=' . $result['_source']['id']
					. '\">' . $result['_source']['name'] . '</a><br />' . $result['_source']['city'] . '");
				resultLatLngs.push([' . $result['_source']['coordinates'][1] . ',' . $result['_source']['coordinates'][0] . ']);
			</script>';
		}
		
		echo '<dl>';
		
		/*
		 * Iterate through every key/value pair contained within each result.
		 */
		foreach ($result['_source'] as $key => $value)
		{
							
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
			
			if ( ($key == 'coordinates') && is_array($value) )
			{
				$value = round($value[1], 4) . ', ' . round($value[0], 4);
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
				$value = '<a href="/search/?q=' . urlencode($value) . '">' . $value . '</a>';
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
	if ($p > 9)
	{
		$start_page = $p - 10;
	}
	else
	{
		$start_page = $p;
	}
	$j=0;
	for ($i=$start_page; $i<$total_pages; $i++)
	{
	
		if ($i != $p)
		{
		
			echo '<li><a href="/search/?';
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
			if (isset($gnis_id))
			{
				echo 'place=' . urlencode($gnis_id) . '&amp;';
			}
			echo 'p=' . $i . '">' . $i . '</a></li>';
		}
		else
		{
			echo '<li>' . $i . '</li>';
		}
		
		if ($j == 20)
		{	
			break;
		}
		
		$j++;
		
	}
	echo '</ul>';
	
	/*
	 * This is the JavaScript that adjusts the map's boundaries to shrink to fit its markers.
	 */
	echo '
		<script>
			var bounds = new L.LatLngBounds(resultLatLngs);
			map.fitBounds(bounds)
		</script>';
	
}

?>

				</article>
			</div> <!-- #main -->
		</div> <!-- #main-container -->

		<div class="footer-container">
			<footer class="wrapper">
				Data last updated on <?php echo date('F d, Y, g:i a', filectime('1_tables.csv') ); ?>.
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
