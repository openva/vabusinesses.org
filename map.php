<?php

require 'includes/header.php';

$template = new Smarty;

$browser_title = 'Map of Virginia businesses';
$page_title = 'Map of Virginia businesses';
$page_summary = 'Every registered business in the Commonwealth that has not expired.';

$summary_file = __DIR__ . '/data/map/summary.json';
$summary = is_readable($summary_file) ? json_decode(file_get_contents($summary_file), TRUE) : NULL;

if (MAP_TILES === '' || !is_array($summary))
{
    /*
     * The map data is built weekly and is not part of the deployment, so it may
     * legitimately be absent -- say so rather than showing an empty frame.
     */
    $page_body = '
    <p>The map is not available at the moment. It is rebuilt each week from the
    Commission&rsquo;s data; please try again later.</p>';
}
else
{
    $page_body = '
    <div id="statewide-map"'
        . ' data-static-api="' . htmlspecialchars(STATIC_API_URL, ENT_QUOTES, 'UTF-8') . '"'
        . ' data-tiles="' . htmlspecialchars(MAP_TILES, ENT_QUOTES, 'UTF-8') . '"'
        . ' data-attribution="' . htmlspecialchars(MAP_ATTRIBUTION, ENT_QUOTES, 'UTF-8') . '"'
        . '></div>
    <p id="map-status" class="meta">Loading&hellip;</p>

    <section>
        <h2>How to read this</h2>
        <p>Each locality is shaded by how many businesses are registered there,
        so the cities and the Northern Virginia suburbs are darkest. Select a
        locality to load the businesses within it, and select any one of those to
        see its record.</p>
        <p>A business appears here if it is registered and has not expired &mdash;
        including those behind on their annual fees &mdash; and if its address
        could be matched to a location. About a third of addresses cannot be, so
        the map undercounts.</p>
    </section>';

    /*
     * The busiest localities, as a way into the map for anyone who does not know
     * where to click.
     */
    $places = array_slice($summary['places'], 0, 10);

    if (!empty($places))
    {
        $page_body .= '
    <section>
        <h2>Where the businesses are</h2>
        <table>
            <caption class="visually-hidden">Localities with the most registered businesses</caption>
            <thead>
                <tr>
                    <th scope="col">Locality</th>
                    <th scope="col" class="numeric">Businesses</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($places as $place)
        {
            $page_body .= '
                <tr>
                    <td>' . htmlspecialchars($place['name'], ENT_QUOTES, 'UTF-8') . '</td>
                    <td class="numeric">' . number_format($place['count']) . '</td>
                </tr>';
        }

        $page_body .= '
            </tbody>
        </table>
    </section>';
    }
}

$template->assign('needs_map', TRUE);
$template->assign('needs_statewide_map', TRUE);
$template->assign('page_body', $page_body);
$template->assign('page_title', $page_title);
$template->assign('page_summary', $page_summary);
$template->assign('browser_title', $browser_title);

$template->display('includes/templates/simple.tpl');
