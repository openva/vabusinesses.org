<?php

/**
 * Bootstrap file
 *
 * This file is loaded at the head of each page, setting up the environment.
 */

/*
 * Define our include path, saving it in a constant so we can call it explicitly elsewhere.
 */
define('INCLUDE_PATH', $_SERVER['DOCUMENT_ROOT'] . 'includes/');
set_include_path(get_include_path() . PATH_SEPARATOR . INCLUDE_PATH);

/*
 * Include our site settings.
 */
require 'settings.php';

/*
 * Include our function library.
 */
require 'functions.php';

/*
 * Include our YAML library. We were using the PECL extension, but it broke mysteriously.
 */
require 'spyc.php';

/*
 * If Memcached use is enabled in settings.php, then set it up and make $mc available globally.
 */
if (MEMCACHED === TRUE)
{
	$mc = new Memcached();
	$mc->addServer("127.0.0.1", 11211);
	global $mc;
}

/*
 * Create an instance of Elasticsearch.
 */
require 'vendor/autoload.php';
$es = new Elasticsearch\Client();

/*
 * Import all YAML table maps and turn them into PHP arrays. First, check Memcached.
 */
$tables = $mc->get('table-maps');
if ($tables === FALSE)
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
		$tables[$file_number] = spyc_load_file($dir . $file);

	}

	/*
	 * Cache the table maps in Memcached, for 24 hours.
	 */
	$mc->set('table-maps', serialize($tables), 86400);
	
}
else
{

	$tables = unserialize($tables);
}

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
