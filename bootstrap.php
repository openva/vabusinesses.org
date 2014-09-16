<?php

/*
 * Define our include path, saving it in a constant so we can call it explicitly elsewhere.
 */
define('INCLUDE_PATH', '/vol/www/business.openva.com/htdocs/includes/');
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
 * If Memcached use is enabled in settings.php, then set it up and make $mc available globally.
 */
if (MEMCACHED === TRUE)
{
	$mc = new Memcached();
	$mc->addServer("127.0.0.1", 11211);
	global $mc;
}
