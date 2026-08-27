<?php

require 'vendor/autoload.php';

$router = new AltoRouter();

/*
 * AltoRouter's built-in "a" match type is [0-9A-Za-z]++, which cannot carry a
 * space -- so a search for "american brands" matched no route at all and 404ed.
 * Business names also contain commas, periods, ampersands and apostrophes.
 *
 * "search" accepts anything except a slash. That is safe because nothing is
 * interpolated into SQL: Business::search() binds the term to a prepared
 * statement and escapes the LIKE metacharacters itself. The restrictive match
 * type was never the injection defence, only an accident of it.
 */
$router->addMatchTypes(array('search' => '[^/]++'));

/*
 * Map our routes
 */

$router->map( 'GET', '/', function()
{
    require __DIR__ . '/home.php';
}, 'home');

$router->map( 'GET', '/business/[a:id]', function($id)
{
    require __DIR__ . '/business.php';
}, 'business-details' );

$router->map( 'GET', '/search/', function()
{
    require __DIR__ . '/search.php';
}, 'search' );

/*
 * The bulk data files live in data/, and the rewrite in .htaccess serves any
 * real file directly -- so /data/corp.csv is the file itself, while /data/ falls
 * through to here and lists them.
 */
$router->map( 'GET', '/data/', function()
{
    require __DIR__ . '/data.php';
}, 'data' );

$router->map( 'GET', '/api/business/[a:id]', function($id)
{
    require __DIR__ . '/api/business.php';
}, 'api-business-details' );

$router->map( 'GET', '/api/search/[search:query]', function($query)
{
    require __DIR__ . '/api/search.php';
}, 'api-search' );

$router->map( 'GET', '/api/recent', function()
{
    require __DIR__ . '/api/recent.php';
}, 'api-recent');

$match = $router->match();

if ( is_array($match) && is_callable( $match['target'] ) )
{
	call_user_func_array( $match['target'], $match['params'] ); 
}

/*
 * 404
 */
else
{
    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
}
