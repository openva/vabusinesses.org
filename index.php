<?php

require 'vendor/autoload.php';

$router = new AltoRouter();

/*
 * Map our routes
 */

$router->map( 'GET', '/', function()
{
    require __DIR__ . '/home.php';
});

$router->map( 'GET', '/business/[a:id]', function($id)
{
    require __DIR__ . '/business.php';
}, 'business-details' );

$router->map( 'GET', '/search/', function()
{
    require __DIR__ . '/search.php';
}, 'search' );

$router->map( 'GET', '/api/business/[a:id]', function($id)
{
    require __DIR__ . '/api/business.php';
}, 'api-business-details' );

$router->map( 'GET', '/api/search/[a:query]', function($query)
{
    require __DIR__ . '/api/search.php';
}, 'api-search' );

$router->map( 'GET', '/api/recent', function()
{
    require __DIR__ . '/api/recent.php';
});

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

