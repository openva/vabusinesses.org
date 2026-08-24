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

    /*
     * When the request goes to the loopback address, Apache still has to be told
     * which of the sites it hosts is being asked for, so send this site's own
     * hostname as the Host header. API_HOST is empty when VABUSINESSES_API_URL
     * names a host explicitly, in which case curl derives the header itself.
     */
    if (defined('API_HOST') && API_HOST !== '')
    {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Host: ' . API_HOST));
    }

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
 * The hostnames this site answers to. The first is the canonical one, used when
 * SERVER_NAME cannot be trusted. Add any alias the site is also served under.
 */
$known_hosts = array(
    'vabusinesses.org',
    'www.vabusinesses.org',
    'localhost',
);

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
     * Connect to the loopback address, but send this site's own hostname as the
     * Host header (see get_content()). The address cannot be influenced by the
     * client, and the Host header is what lets Apache pick this site out of the
     * several it serves -- requesting the server's IP directly would land on
     * whichever virtual host happens to be the default.
     *
     * The hostname comes from SERVER_NAME, which reflects the vhost's ServerName
     * when UseCanonicalName is On. It is deliberately compared against a list of
     * hostnames this site answers to, because with UseCanonicalName Off (the
     * Apache default) SERVER_NAME is taken from the client's Host header -- and
     * an unchecked value there would let a request redirect this server's own
     * API calls to a host of the client's choosing.
     */
    $api_host = $_SERVER['SERVER_NAME'] ?? '';

    if (!in_array($api_host, $known_hosts, TRUE))
    {
        $api_host = $known_hosts[0];
    }

    define('API_HOST', $api_host);
    $api_url = 'http://127.0.0.1';
}
else
{
    define('API_HOST', '');
}

define('API_URL', rtrim($api_url, '/'));
