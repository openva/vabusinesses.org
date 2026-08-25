<?php

/**
 * Generate includes/localities.json from municipalities.geojson.
 *
 * RA-Loc holds Virginia FIPS county and city codes, but the SCC's own lookup
 * table covers only a seventh of the codes that appear in the data. The Census
 * TIGER file in the repository root has the rest.
 *
 * The mapping is extracted rather than read at runtime because the geojson is
 * 1.5 MB of geometry: parsing it costs about 18ms and 20MB per request to
 * recover 133 names, against 0.07ms for the extracted file.
 *
 * Usage: php scripts/build-localities.php
 */

$root = dirname(__DIR__);
$source = $root . '/municipalities.geojson';
$target = $root . '/includes/localities.json';

$geojson = @file_get_contents($source);

if ($geojson === false)
{
    fwrite(STDERR, "build-localities.php: cannot read $source\n");
    exit(1);
}

$parsed = json_decode($geojson, true);

if (!isset($parsed['features']) || !is_array($parsed['features']))
{
    fwrite(STDERR, "build-localities.php: $source has no features\n");
    exit(1);
}

$localities = array();

foreach ($parsed['features'] as $feature)
{
    $properties = $feature['properties'] ?? array();

    /*
     * 51 is Virginia. The file should hold nothing else, but a national TIGER
     * extract would.
     */
    if (($properties['STATEFP'] ?? '') !== '51')
    {
        continue;
    }

    $code = $properties['COUNTYFP'] ?? '';
    $name = $properties['NAMELSAD'] ?? '';

    if ($code === '' || $name === '')
    {
        continue;
    }

    /*
     * TIGER writes "Richmond city" but "Fairfax County"; capitalise the former
     * so the two read alike.
     */
    $localities[$code] = preg_replace('/\bcity\b/', 'City', $name);
}

if (count($localities) < 100)
{
    fwrite(STDERR, 'build-localities.php: only ' . count($localities) . " localities found, expected ~133\n");
    exit(1);
}

ksort($localities);

$written = file_put_contents(
    $target,
    json_encode($localities, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

if ($written === false)
{
    fwrite(STDERR, "build-localities.php: cannot write $target\n");
    exit(1);
}

echo 'Wrote ' . count($localities) . " localities to includes/localities.json\n";
