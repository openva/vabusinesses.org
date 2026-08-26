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
     * The URL names this site's own hostname, so that Apache can pick this site
     * out of the several it may serve -- but the connection is pinned to the
     * loopback address rather than going out to DNS and back around through any
     * proxy or load balancer in front of the site.
     *
     * CURLOPT_RESOLVE is used in preference to sending a Host header via
     * CURLOPT_HTTPHEADER: that option replaces curl's entire default header
     * list, so it drops any header curl would otherwise generate.
     */
    if (defined('API_RESOLVE') && API_RESOLVE !== '')
    {
        curl_setopt($ch, CURLOPT_RESOLVE, array(API_RESOLVE));
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
 * Map tiles.
 *
 * This token is committed deliberately. A Mapbox "pk." token is sent by the
 * browser when it fetches tiles, so it is visible in the page source and in the
 * network tab however it is stored -- Mapbox designs public tokens to be
 * exposed this way, and hiding it in a secret would not change who can read it.
 *
 * What does protect it is configured at Mapbox, not here: a URL restriction, so
 * the token only works for requests from this site, and the styles:tiles scope.
 * Both matter, because forks of this repository carry this token and would
 * otherwise draw against this account's quota.
 *
 * The environment takes precedence, for local development against a different
 * token or style.
 */
define('MAPBOX_TOKEN', getenv('MAPBOX_TOKEN') ?: 'pk.eyJ1Ijoid2FsZG9qIiwiYSI6ImNtdDlpMjhsazA4OG0yeXE3aTVmZzczbHMifQ.em5tEyvpuil7IFDumXN6-w');
define('MAPBOX_STYLE', getenv('MAPBOX_STYLE') ?: 'mapbox/streets-v12');

define(
    'MAP_TILES',
    MAPBOX_TOKEN === ''
        ? ''
        : 'https://api.mapbox.com/styles/v1/' . MAPBOX_STYLE
            . '/tiles/512/{z}/{x}/{y}@2x?access_token=' . MAPBOX_TOKEN
);

define(
    'MAP_ATTRIBUTION',
    '&copy; <a href="https://www.mapbox.com/about/maps/">Mapbox</a> '
    . '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
);

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
 * The request is addressed to this site by name -- the server hosts several
 * sites, so Apache needs the hostname to route it -- but pinned to the loopback
 * address, so it cannot leave the machine no matter what that name resolves to.
 *
 * Set VABUSINESSES_API_URL to override, for a setup where the site is not
 * reachable that way from the web server itself.
 */
$api_url = getenv('VABUSINESSES_API_URL');

if ($api_url === false || $api_url === '')
{
    /*
     * SERVER_NAME reflects the vhost's ServerName when UseCanonicalName is On,
     * but is taken from the client's Host header when it is Off (the Apache
     * default). It is therefore checked against the hostnames this site answers
     * to: an unvalidated value would let a request carrying, say,
     * "Host: 169.254.169.254" point this server's own API calls wherever the
     * client liked.
     */
    $api_host = $_SERVER['SERVER_NAME'] ?? '';

    if (!in_array($api_host, $known_hosts, TRUE))
    {
        $api_host = $known_hosts[0];
    }

    /*
     * Ask for this site by name, but resolve that name to the loopback address
     * so the request cannot leave the machine.
     */
    $api_url = 'http://' . $api_host;
    define('API_RESOLVE', $api_host . ':80:127.0.0.1');
}
else
{
    define('API_RESOLVE', '');
}

define('API_URL', rtrim($api_url, '/'));
