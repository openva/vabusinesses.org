<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

/*
 * Define the function for API queries, etc.
 */
function get_content($url)
{

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $string = curl_exec($ch);
    curl_close($ch);

    if (empty($string))
    {
        return false;
    }

    return $string;

}

/*
 * Render a file's size for display, in the largest sensible unit
 */
function human_filesize($path)
{

    $bytes = @filesize($path);

    if ($bytes === false)
    {
        return '';
    }

    if ($bytes >= 1073741824)
    {
        return round($bytes / 1073741824, 1) . ' GB';
    }

    if ($bytes >= 1048576)
    {
        return round($bytes / 1048576) . ' MB';
    }

    if ($bytes >= 1024)
    {
        return round($bytes / 1024) . ' KB';
    }

    return $bytes . ' bytes';

}

/*
 * Identify the prefix for our own API queries.
 *
 * Every page here fetches its data from this site's own public API over HTTP,
 * rather than calling the Business class directly. That round trip is
 * deliberate, not an oversight: it means the site is a consumer of the same API
 * third parties use, so any breakage in it shows up on the website immediately
 * instead of being reported by someone else weeks later. Please do not
 * "optimise" this into a direct call.
 *
 * Because the request is made by the server to itself, the address has to
 * resolve back to this server. It deliberately does not use SERVER_NAME, which
 * reflects the client's Host header: sending "Host: 169.254.169.254" would
 * otherwise make the server fetch from an address of the client's choosing --
 * a server-side request forgery, and on EC2 a route to the instance metadata
 * service. SERVER_ADDR is the server's own address and cannot be set by a
 * client.
 *
 * Set VABUSINESSES_API_URL to override, for a setup where the site is not
 * reachable at its own address from the web server itself.
 */
$api_url = getenv('VABUSINESSES_API_URL');

if ($api_url === false || $api_url === '')
{
    /*
     * SERVER_ADDR is the address this server is actually reachable at, unlike
     * SERVER_PORT, which reports the port the *client* connected to -- behind a
     * port mapping or a proxy that is not a port this server listens on.
     */
    $api_url = 'http://' . ($_SERVER['SERVER_ADDR'] ?? '127.0.0.1');
}

define('API_URL', rtrim($api_url, '/'));
