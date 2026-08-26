<?php

/**
 * Coordinates for an address, from the geocode cache in data/addresses.db.
 *
 * That cache is keyed by an MD5 of a normalised address string. The recipe is a
 * compatibility contract with the geocoder that populates it (the `crump`
 * project, crumplib/geocache.py): change it here and every lookup misses
 * silently. It is, verbatim:
 *
 *     md5( STREET1 , STREET2 , CITY , ST , ZIP5 )
 *
 * with each part uppercased and whitespace-collapsed, the state reduced to its
 * USPS abbreviation, and the ZIP truncated at the hyphen. Note that STREET2 is
 * included even when empty, which is why the key for a one-line address has two
 * consecutive commas.
 */
class Geocode
{

    public $db;

    /*
     * The geocode cache was built when the SCC feed abbreviated states; the CSV
     * feed spells them out. Reducing them here is what makes the cache usable.
     */
    const STATES = array(
        'alabama' => 'AL',
        'alaska' => 'AK',
        'american samoa' => 'AS',
        'arizona' => 'AZ',
        'arkansas' => 'AR',
        'california' => 'CA',
        'colorado' => 'CO',
        'connecticut' => 'CT',
        'delaware' => 'DE',
        'district of columbia' => 'DC',
        'florida' => 'FL',
        'georgia' => 'GA',
        'guam' => 'GU',
        'hawaii' => 'HI',
        'idaho' => 'ID',
        'illinois' => 'IL',
        'indiana' => 'IN',
        'iowa' => 'IA',
        'kansas' => 'KS',
        'kentucky' => 'KY',
        'louisiana' => 'LA',
        'maine' => 'ME',
        'maryland' => 'MD',
        'massachusetts' => 'MA',
        'michigan' => 'MI',
        'minnesota' => 'MN',
        'mississippi' => 'MS',
        'missouri' => 'MO',
        'montana' => 'MT',
        'nebraska' => 'NE',
        'nevada' => 'NV',
        'new hampshire' => 'NH',
        'new jersey' => 'NJ',
        'new mexico' => 'NM',
        'new york' => 'NY',
        'north carolina' => 'NC',
        'north dakota' => 'ND',
        'northern mariana islands' => 'MP',
        'ohio' => 'OH',
        'oklahoma' => 'OK',
        'oregon' => 'OR',
        'pennsylvania' => 'PA',
        'puerto rico' => 'PR',
        'rhode island' => 'RI',
        'south carolina' => 'SC',
        'south dakota' => 'SD',
        'tennessee' => 'TN',
        'texas' => 'TX',
        'utah' => 'UT',
        'vermont' => 'VT',
        'virgin islands' => 'VI',
        'virginia' => 'VA',
        'washington' => 'WA',
        'west virginia' => 'WV',
        'wisconsin' => 'WI',
        'wyoming' => 'WY',
    );

    /**
     * Open the geocode cache, or FALSE when it is not present.
     *
     * The cache is optional: it is not part of the deployment bundle, and a
     * missing file means no maps rather than a broken page.
     */
    function connect()
    {
        $file = $_SERVER['DOCUMENT_ROOT'] . '/data/addresses.db';

        if (!is_readable($file))
        {
            return FALSE;
        }

        $this->db = new SQLite3($file, SQLITE3_OPEN_READONLY);

        return $this->db;
    }

    /**
     * Collapse whitespace and strip the tab the SCC prefixes to some fields
     */
    static function clean($value)
    {
        return trim(preg_replace('/\s+/', ' ', str_replace("\t", ' ', (string) $value)));
    }

    /**
     * Reduce a state name to its USPS abbreviation
     */
    static function abbreviate($state)
    {
        $state = self::clean($state);

        if ($state === '')
        {
            return '';
        }

        if (strlen($state) === 2)
        {
            return strtoupper($state);
        }

        return self::STATES[strtolower($state)] ?? $state;
    }

    /**
     * Take the five-digit portion of a ZIP, discarding all-zero placeholders
     */
    static function zip5($zip)
    {
        $zip = strtoupper(str_replace(' ', '', self::clean($zip)));

        if ($zip === '' || preg_match('/^[0-]+$/', $zip))
        {
            return '';
        }

        return explode('-', $zip)[0];
    }

    /**
     * The MD5 that data/addresses.db is keyed by
     */
    static function hash($street_1, $street_2, $city, $state, $zip)
    {
        $key = implode(',', array(
            strtoupper(self::clean($street_1)),
            strtoupper(self::clean($street_2)),
            strtoupper(self::clean($city)),
            strtoupper(self::abbreviate($state)),
            self::zip5($zip),
        ));

        return md5($key);
    }

    /**
     * Look up coordinates for an address
     *
     * @return array|false array('latitude' => float, 'longitude' => float), or
     *                     FALSE when the address has not been geocoded
     */
    function coordinates($street_1, $street_2, $city, $state, $zip)
    {
        if (!isset($this->db))
        {
            return FALSE;
        }

        /*
         * An address with no street or no city cannot have been geocoded, and
         * hashing it would just be a guaranteed miss.
         */
        if (self::clean($street_1) === '' || self::clean($city) === '')
        {
            return FALSE;
        }

        $statement = $this->db->prepare(
            'SELECT latitude, longitude
            FROM addresses
            WHERE address_hash = :hash
            AND latitude IS NOT NULL
            AND longitude IS NOT NULL'
        );

        if ($statement === FALSE)
        {
            return FALSE;
        }

        $statement->bindValue(':hash', self::hash($street_1, $street_2, $city, $state, $zip), SQLITE3_TEXT);

        $result = $statement->execute();

        if ($result === FALSE)
        {
            return FALSE;
        }

        $row = $result->fetchArray(SQLITE3_ASSOC);

        if (!is_array($row))
        {
            return FALSE;
        }

        return array(
            'latitude'  => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
        );
    }

}
