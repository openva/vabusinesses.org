<?php

/**
 * Autoload any class file when it is called.
 */
function __autoload_libraries($class_name)
{

	$filename = 'class.' . $class_name . '.php';
	if (file_exists(INCLUDE_PATH . $filename) == TRUE)
	{
		$result = include_once $filename;
	}
	
	return;

}

spl_autoload_register('__autoload_libraries');

/*
 * Calculate file size in human-readable terms.
 */
function human_filesize($bytes, $decimals = 0)
{
	$sz = 'BKMGTP';
	$factor = floor((strlen($bytes) - 1) / 3);
	return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
}

/*
 * Import the list of place names.
 */
function import_place_names()
{

	$json = file_get_contents('includes/place_names.json');
	if ($json == FALSE)
	{
		return FALSE;
	}
	$places = json_decode($json);
	$places = (array) $places;
	asort($places);
	$places = (object) $places;
	return $places;
	
}
 