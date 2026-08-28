<?php

/**
 * Build the data behind the map: a per-locality summary, and one file of
 * coordinates per locality.
 *
 * Nearly a million businesses are registered and not expired, of which about
 * two thirds have been geocoded. Shipping those to a browser as one file would
 * be tens of megabytes, so the work is split:
 *
 *   data/map/summary.json    one row per locality, for the choropleth
 *   data/map/<fips>.json     the points in that locality, fetched on demand
 *
 * Coordinates are stored as integers scaled by 1000 -- three decimal places,
 * about 110 metres -- in a flat array rather than as pairs. At the zoom levels
 * this map is read at, the precision is invisible, and the encoding costs about
 * 12 bytes per point instead of 36.
 *
 * Points are grouped by location rather than by business: three quarters of
 * businesses share an address with another, so one marker each would stack them
 * unclickably. "i" holds the identifiers at each location, so i[n] belongs to
 * the coordinate pair at p[2n] and p[2n+1].
 *
 * Usage: php scripts/build-map-data.php
 */

$root = dirname(__DIR__);
$output = $root . '/data/map';

/*
 * Records that are ACTIVE or PENDINACT. PENDINACT means a business is behind on
 * its fees or annual report but is still registered -- not expired.
 */
$statuses = "('ACTIVE', 'PENDINACT')";

$tables = array('corp', 'llc', 'lp', 'gp', 'bt', 'psa');

/* ---------------------------------------------------------------- helpers */

/**
 * Which locality a point falls in, by ray casting against each boundary.
 */
function locality_for($longitude, $latitude, $localities)
{
    foreach ($localities as $fips => $locality)
    {
        /*
         * The bounding box rejects the overwhelming majority of candidates
         * without walking the ring, which matters at ~670,000 points.
         */
        if ($longitude < $locality['bbox'][0] || $longitude > $locality['bbox'][2]
            || $latitude < $locality['bbox'][1] || $latitude > $locality['bbox'][3])
        {
            continue;
        }

        foreach ($locality['rings'] as $ring)
        {
            if (point_in_ring($longitude, $latitude, $ring))
            {
                return $fips;
            }
        }
    }

    return NULL;
}

function point_in_ring($x, $y, $ring)
{
    $inside = FALSE;
    $count = count($ring);

    for ($i = 0, $j = $count - 1; $i < $count; $j = $i++)
    {
        $xi = $ring[$i][0];
        $yi = $ring[$i][1];
        $xj = $ring[$j][0];
        $yj = $ring[$j][1];

        if ((($yi > $y) !== ($yj > $y))
            && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi))
        {
            $inside = !$inside;
        }
    }

    return $inside;
}

/* ------------------------------------------------------------ boundaries */

$geojson = @file_get_contents($root . '/municipalities.geojson');

if ($geojson === FALSE)
{
    fwrite(STDERR, "build-map-data.php: cannot read municipalities.geojson\n");
    exit(1);
}

$parsed = json_decode($geojson, TRUE);
$localities = array();

foreach ($parsed['features'] as $feature)
{
    $properties = $feature['properties'];

    if (($properties['STATEFP'] ?? '') !== '51')
    {
        continue;
    }

    $rings = array();

    /*
     * A locality is a Polygon or, where it has islands, a MultiPolygon. Only the
     * outer ring of each part is needed: holes are rare and cost more to handle
     * than they save.
     */
    $geometry = $feature['geometry'];

    if ($geometry['type'] === 'Polygon')
    {
        $rings[] = $geometry['coordinates'][0];
    }
    elseif ($geometry['type'] === 'MultiPolygon')
    {
        foreach ($geometry['coordinates'] as $polygon)
        {
            $rings[] = $polygon[0];
        }
    }

    $min_x = $min_y = INF;
    $max_x = $max_y = -INF;

    foreach ($rings as $ring)
    {
        foreach ($ring as $point)
        {
            $min_x = min($min_x, $point[0]);
            $max_x = max($max_x, $point[0]);
            $min_y = min($min_y, $point[1]);
            $max_y = max($max_y, $point[1]);
        }
    }

    $localities[$properties['COUNTYFP']] = array(
        'name'  => preg_replace('/\bcity\b/', 'City', $properties['NAMELSAD']),
        'rings' => $rings,
        'bbox'  => array($min_x, $min_y, $max_x, $max_y),
        'centre' => array(
            round(($min_x + $max_x) / 2, 4),
            round(($min_y + $max_y) / 2, 4),
        ),
    );
}

echo 'Loaded ' . count($localities) . " locality boundaries\n";

/* --------------------------------------------------------------- geocode */

require $root . '/includes/class.Geocode.php';

$_SERVER['DOCUMENT_ROOT'] = $root;

$geocode = new Geocode;

if ($geocode->connect() === FALSE)
{
    fwrite(STDERR, "build-map-data.php: data/addresses.db not found; nothing to map\n");
    exit(1);
}

$business_db = $root . '/data/vabusinesses.sqlite';

if (!is_readable($business_db))
{
    fwrite(STDERR, "build-map-data.php: data/vabusinesses.sqlite not found\n");
    exit(1);
}

$db = new SQLite3($business_db, SQLITE3_OPEN_READONLY);

if (!is_dir($output))
{
    mkdir($output, 0755, TRUE);
}

$locations = array();
$total = $located = $unlocated = 0;

foreach ($tables as $table)
{
    $result = $db->query(
        'SELECT EntityID, Street1, Street2, City, State, Zip
        FROM ' . $table . '
        WHERE Status IN ' . $statuses . '
        AND Street1 <> ""
        AND City <> ""'
    );

    while ($row = $result->fetchArray(SQLITE3_ASSOC))
    {
        $total++;

        $point = $geocode->coordinates(
            $row['Street1'],
            $row['Street2'],
            $row['City'],
            $row['State'],
            $row['Zip']
        );

        if ($point === FALSE)
        {
            continue;
        }

        $fips = locality_for($point['longitude'], $point['latitude'], $localities);

        if ($fips === NULL)
        {
            /*
             * Out-of-state addresses, mostly: a Virginia business may register
             * from anywhere. They have coordinates but no Virginia locality.
             */
            $unlocated++;
            continue;
        }

        /*
         * Group by rounded coordinate. Three quarters of businesses share an
         * address with at least one other -- office buildings, registered agent
         * offices, shopping centres -- and drawing one marker each stacks them
         * so that only the topmost can be clicked. One marker per location, with
         * every business at that location behind it, is both usable and smaller:
         * Fairfax drops from 31,644 markers to 13,007.
         */
        $key = ((int) round($point['latitude'] * 1000))
            . ',' . ((int) round($point['longitude'] * 1000));

        $locations[$fips][$key][] = trim($row['EntityID']);

        $located++;
    }

    echo '  ' . $table . ": $located located so far\n";
}

/* ---------------------------------------------------------------- output */

$summary = array();

foreach ($localities as $fips => $locality)
{
    $places = $locations[$fips] ?? array();

    /*
     * The headline number is businesses, not locations: "31,644 businesses in
     * Fairfax County" is the useful figure, even though they are drawn as 13,007
     * markers.
     */
    $count = 0;

    foreach ($places as $identifiers)
    {
        $count += count($identifiers);
    }

    $summary[] = array(
        'fips'   => $fips,
        'name'   => $locality['name'],
        'count'  => $count,
        'centre' => $locality['centre'],
    );

    if ($count === 0)
    {
        continue;
    }

    /*
     * "p" holds one coordinate pair per location, flat; "i" holds the
     * identifiers at each, so i[n] belongs to the location at p[2n], p[2n+1].
     */
    $coordinates = array();
    $identifiers = array();

    foreach ($places as $key => $list)
    {
        $pair = explode(',', $key);

        $coordinates[] = (int) $pair[0];
        $coordinates[] = (int) $pair[1];
        $identifiers[] = $list;
    }

    file_put_contents(
        $output . '/' . $fips . '.json',
        json_encode(array(
            'n' => $count,
            'p' => $coordinates,
            'i' => $identifiers,
        ))
    );
}

usort($summary, function ($a, $b) { return $b['count'] - $a['count']; });

file_put_contents(
    $output . '/summary.json',
    json_encode(array(
        'built'  => date('c'),
        'total'  => $located,
        'places' => $summary,
    ))
);

printf(
    "\n%s businesses considered, %s mapped, %s outside Virginia\n",
    number_format($total),
    number_format($located),
    number_format($unlocated)
);
printf("Wrote %s of map data to data/map/\n", human_size(array_sum(array_map(
    function ($f) { return filesize($f); },
    glob($output . '/*.json')
))));

function human_size($bytes)
{
    if ($bytes >= 1048576)
    {
        return round($bytes / 1048576, 1) . ' MB';
    }

    return round($bytes / 1024) . ' KB';
}
