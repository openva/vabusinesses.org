<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/header.php';

use PHPUnit\Framework\TestCase;

class BusinessTest extends PHPUnit\Framework\TestCase
{

    public function getConnection()
    {
        $database = new Database;
        return $database->connect();
    }

    public function testValidCorpIdIsValid()
    {
        $corp_id = '0848677';

        $business = new Business();
        $result = $business->id_is_valid($corp_id);

        $this->assertTrue($result);
    }

    public function testInvalidCorpIdIsInvalid()
    {
        $corp_id = 'abcdefg';

        $business = new Business();
        $result = $business->id_is_valid($corp_id);

        $this->assertFalse($result);
    }

    public function testCorpIdIsIdentified()
    {
        $corp_id = '0848677';

        $business = new Business();
        $result = $business->type_from_id($corp_id);

        $this->assertEquals('corp', $result);
    }

    public function testLlcIdIsIdentified()
    {
        $corp_id = 'S813148';

        $business = new Business();
        $result = $business->type_from_id($corp_id);

        $this->assertEquals('llc', $result);
    }

    public function testLpIdIsIdentified()
    {
        $corp_id = 'L020420';

        $business = new Business();
        $result = $business->type_from_id($corp_id);

        $this->assertEquals('lp', $result);
    }

    public function testInvalidIdIsNotIdentified()
    {
        $corp_id = 'G123456';

        $business = new Business();
        $result = $business->type_from_id($corp_id);

        $this->assertFalse($result);
    }

    /**
     * RA-Loc is a FIPS code, and FIPS has to win where the two lists collide.
     *
     * The SCC's court-locality table numbers localities alphabetically and FIPS
     * numbers its own alphabetical sequence in odd steps, so the two disagree on
     * 43 of the 44 codes they share. Reading 131 from the SCC's list filed a
     * library in Nassawadox under Floyd County, on the far side of the state.
     */
    public function testFipsLocalityCodesOutrankTheSccTable()
    {
        $business = new Business();
        $localities = $business->lookup_table()['court-locality-code'];

        $this->assertEquals('Northampton County', $localities['131']);
        $this->assertEquals('King William County', $localities['101']);
        $this->assertEquals('Lancaster County', $localities['103']);
    }

    /**
     * The SCC's list is still the only source for the codes FIPS does not
     * define, which are the ones that name something other than a place.
     */
    public function testNonFipsLocalityCodesSurvive()
    {
        $business = new Business();
        $localities = $business->lookup_table()['court-locality-code'];

        $this->assertEquals('EXEMPT', $localities['901']);
    }

}
