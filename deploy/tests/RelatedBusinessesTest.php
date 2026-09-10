<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/header.php';

use PHPUnit\Framework\TestCase;

class RelatedBusinessesTest extends PHPUnit\Framework\TestCase
{

    /*
     * A coordinate pair with the precision the geocoder actually returns, so
     * that these tests exercise the same float comparison production does.
     */
    const LATITUDE = 37.5385938555998;
    const LONGITUDE = -77.4366930947503;

    /**
     * Build a database holding only what these tests need.
     *
     * In memory, and populated per test, rather than against
     * data/vabusinesses.sqlite: the real database is a gigabyte, is rebuilt
     * weekly from whatever the SCC published, and holds no address whose
     * occupants can be asserted on from one week to the next.
     */
    private function database(array $rows)
    {
        $db = new SQLite3(':memory:');

        foreach (Business::ENTITY_TABLES as $table)
        {
            $db->exec(
                'CREATE TABLE ' . $table . ' (
                    EntityID TEXT, Name TEXT, Status TEXT,
                    Latitude REAL, Longitude REAL
                )'
            );
        }

        foreach ($rows as $row)
        {
            $statement = $db->prepare(
                'INSERT INTO ' . $row['table'] . '
                (EntityID, Name, Status, Latitude, Longitude)
                VALUES (:id, :name, :status, :latitude, :longitude)'
            );

            $statement->bindValue(':id', $row['id'], SQLITE3_TEXT);
            $statement->bindValue(':name', $row['name'], SQLITE3_TEXT);
            $statement->bindValue(':status', $row['status'] ?? 'ACTIVE', SQLITE3_TEXT);

            /*
             * bindValue() with NULL is how an ungeocoded row is written, so the
             * fixtures write it the same way.
             */
            if (array_key_exists('latitude', $row) && $row['latitude'] === null)
            {
                $statement->bindValue(':latitude', null, SQLITE3_NULL);
                $statement->bindValue(':longitude', null, SQLITE3_NULL);
            }
            else
            {
                $statement->bindValue(
                    ':latitude',
                    $row['latitude'] ?? self::LATITUDE,
                    SQLITE3_FLOAT
                );
                $statement->bindValue(
                    ':longitude',
                    $row['longitude'] ?? self::LONGITUDE,
                    SQLITE3_FLOAT
                );
            }

            $statement->execute();
        }

        return $db;
    }

    /**
     * The identifiers fetch() returns, which is what every test asserts on.
     */
    private function relatedTo($db, $id, $type = null)
    {
        $related = new RelatedBusinesses;
        $related->db = $db;
        $related->id = $id;
        $related->type = $type;

        $results = $related->fetch();

        if (!is_array($results))
        {
            return $results;
        }

        return array_map(
            function ($business) { return $business['EntityID']; },
            $results
        );
    }

    public function testBusinessesAtTheSameCoordinatesAreRelated()
    {
        $db = $this->database(array(
            array('table' => 'corp', 'id' => '00000001', 'name' => 'First'),
            array('table' => 'corp', 'id' => '00000002', 'name' => 'Second'),
        ));

        $this->assertEquals(array('00000002'), $this->relatedTo($db, '00000001'));
    }

    public function testBusinessesAtOtherCoordinatesAreNotRelated()
    {
        $db = $this->database(array(
            array('table' => 'corp', 'id' => '00000001', 'name' => 'First'),
            array(
                'table' => 'corp',
                'id' => '00000002',
                'name' => 'Elsewhere',
                'latitude' => 38.0293,
                'longitude' => -78.4767,
            ),
        ));

        $this->assertEquals(array(), $this->relatedTo($db, '00000001'));
    }

    public function testTheBusinessItselfIsExcluded()
    {
        $db = $this->database(array(
            array('table' => 'corp', 'id' => '00000001', 'name' => 'Only'),
        ));

        $this->assertEquals(array(), $this->relatedTo($db, '00000001'));
    }

    /**
     * A business listed in more than one entity table is one business.
     */
    public function testARecordInTwoTablesIsListedOnce()
    {
        $db = $this->database(array(
            array('table' => 'corp', 'id' => '00000001', 'name' => 'First'),
            array('table' => 'corp', 'id' => '00000002', 'name' => 'Twice'),
            array('table' => 'llc',  'id' => '00000002', 'name' => 'Twice'),
        ));

        $this->assertEquals(array('00000002'), $this->relatedTo($db, '00000001'));
    }

    public function testBusinessesInEveryEntityTableAreFound()
    {
        $rows = array(array('table' => 'corp', 'id' => '00000001', 'name' => 'First'));
        $expected = array();

        foreach (Business::ENTITY_TABLES as $index => $table)
        {
            $id = '1000000' . $index;
            $rows[] = array('table' => $table, 'id' => $id, 'name' => 'Business ' . $index);
            $expected[] = $id;
        }

        $related = $this->relatedTo($this->database($rows), '00000001');

        sort($related);

        $this->assertEquals($expected, $related);
    }

    public function testExpiredBusinessesAreExcluded()
    {
        $db = $this->database(array(
            array('table' => 'corp', 'id' => '00000001', 'name' => 'First'),
            array(
                'table' => 'corp',
                'id' => '00000002',
                'name' => 'Dissolved',
                'status' => 'INACTIVE',
            ),
        ));

        $this->assertEquals(array(), $this->relatedTo($db, '00000001'));
    }

    /**
     * A business behind on its filings is still registered at the address.
     */
    public function testPendingInactiveBusinessesAreIncluded()
    {
        $db = $this->database(array(
            array('table' => 'corp', 'id' => '00000001', 'name' => 'First'),
            array(
                'table' => 'corp',
                'id' => '00000002',
                'name' => 'Behind',
                'status' => 'PENDINACT',
            ),
        ));

        $this->assertEquals(array('00000002'), $this->relatedTo($db, '00000001'));
    }

    /**
     * One more than the limit is returned, so that business.php can tell that
     * the list was cut short rather than presenting it as complete.
     */
    public function testMoreThanTheLimitIsCappedAtOneOverTheLimit()
    {
        $rows = array(array('table' => 'corp', 'id' => '00000001', 'name' => 'First'));

        for ($n = 0; $n < RelatedBusinesses::LIMIT + 10; $n++)
        {
            $rows[] = array(
                'table' => 'corp',
                'id' => sprintf('2%07d', $n),
                'name' => sprintf('Tenant %03d', $n),
            );
        }

        $this->assertCount(
            RelatedBusinesses::LIMIT + 1,
            $this->relatedTo($this->database($rows), '00000001')
        );
    }

    /**
     * Deduplication has to happen before the limit, not after it.
     *
     * An entity the SCC ships in two tables arrives as two rows. If those are
     * collapsed after the limit rather than before it, a duplicate among the
     * rows fetched leaves exactly LIMIT businesses -- indistinguishable from a
     * list that fits, so business.php would present the first fifty of several
     * thousand as though they were all of them.
     */
    public function testADuplicateDoesNotHideTruncation()
    {
        $rows = array(array('table' => 'corp', 'id' => '00000001', 'name' => 'First'));

        for ($n = 0; $n < RelatedBusinesses::LIMIT + 10; $n++)
        {
            $rows[] = array(
                'table' => 'corp',
                'id' => sprintf('2%07d', $n),
                'name' => sprintf('Tenant %03d', $n),
            );
        }

        /*
         * A second copy of the alphabetically first tenant, which is what would
         * otherwise be spent out of the fifty-one rows fetched.
         */
        $rows[] = array(
            'table' => 'llc',
            'id' => sprintf('2%07d', 0),
            'name' => sprintf('Tenant %03d', 0),
        );

        $related = $this->relatedTo($this->database($rows), '00000001');

        $this->assertCount(RelatedBusinesses::LIMIT + 1, $related);
        $this->assertEquals(
            count(array_unique($related)),
            count($related),
            'the same business must not be listed twice'
        );
    }

    public function testAListThatFitsIsNotPaddedToTheLimit()
    {
        $rows = array(array('table' => 'corp', 'id' => '00000001', 'name' => 'First'));

        for ($n = 0; $n < 5; $n++)
        {
            $rows[] = array(
                'table' => 'corp',
                'id' => sprintf('2%07d', $n),
                'name' => sprintf('Tenant %03d', $n),
            );
        }

        $this->assertCount(5, $this->relatedTo($this->database($rows), '00000001'));
    }

    /**
     * About a third of businesses are not in the geocode cache. Two of those
     * are not at the same address as each other; they are at no address.
     */
    public function testUngeocodedBusinessHasNoRelations()
    {
        $db = $this->database(array(
            array('table' => 'corp', 'id' => '00000001', 'name' => 'First',
                  'latitude' => null),
            array('table' => 'corp', 'id' => '00000002', 'name' => 'Second',
                  'latitude' => null),
        ));

        $this->assertEquals(array(), $this->relatedTo($db, '00000001'));
    }

    /**
     * The columns arrive with the weekly rebuild, so between deploying this and
     * the next run the code is pointed at a database that does not have them.
     * That has to be quiet: SQLite3::prepare() raises a PHP warning on an
     * unknown column, and a warning printed into the response body makes the
     * API's JSON unparseable and the business page a 500.
     */
    public function testDatabaseWithoutCoordinateColumns()
    {
        $db = new SQLite3(':memory:');

        foreach (Business::ENTITY_TABLES as $table)
        {
            $db->exec(
                'CREATE TABLE ' . $table . ' (EntityID TEXT, Name TEXT, Status TEXT)'
            );
        }

        $db->exec('INSERT INTO corp (EntityID, Name, Status)
                   VALUES ("00000001", "First", "ACTIVE")');

        $related = new RelatedBusinesses;
        $related->db = $db;
        $related->id = '00000001';

        $this->assertEquals(array(), $related->fetch());
    }

    /**
     * A business that is not in any entity table at all.
     */
    public function testUnknownBusinessHasNoRelations()
    {
        $db = $this->database(array(
            array('table' => 'corp', 'id' => '00000001', 'name' => 'First'),
            array('table' => 'corp', 'id' => '00000002', 'name' => 'Second'),
        ));

        $this->assertEquals(array(), $this->relatedTo($db, '09999999'));
    }

    /**
     * The coordinates are read in SQL rather than passed in because they do not
     * survive being rendered as a string: PHP's default precision of 14
     * significant digits turns 37.538593855599842 into 37.5385938556, which is
     * a different float and matches nothing. This is what that looks like when
     * it goes wrong, and it is the reason fetch() takes an identifier and not a
     * coordinate pair.
     */
    public function testFullPrecisionCoordinatesStillMatch()
    {
        $db = $this->database(array(
            array('table' => 'corp', 'id' => '00000001', 'name' => 'First'),
            array('table' => 'corp', 'id' => '00000002', 'name' => 'Second'),
        ));

        /*
         * The fixtures use a coordinate with more digits than a string cast
         * keeps, so a regression to passing coordinates through PHP would show
         * up here as an empty result.
         */
        $this->assertNotEquals(
            self::LATITUDE,
            (float) (string) self::LATITUDE,
            'this test is only meaningful with a coordinate that a string cast rounds'
        );

        $this->assertEquals(array('00000002'), $this->relatedTo($db, '00000001'));
    }

    public function testResultsAreOrderedByName()
    {
        $db = $this->database(array(
            array('table' => 'corp', 'id' => '00000001', 'name' => 'First'),
            array('table' => 'corp', 'id' => '00000002', 'name' => 'Zebra'),
            array('table' => 'llc',  'id' => '00000003', 'name' => 'apple'),
            array('table' => 'corp', 'id' => '00000004', 'name' => 'Mango'),
        ));

        $this->assertEquals(
            array('00000003', '00000004', '00000002'),
            $this->relatedTo($db, '00000001')
        );
    }

}
