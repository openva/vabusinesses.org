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
