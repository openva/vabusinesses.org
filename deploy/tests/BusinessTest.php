<?php

require('autoloader.php');

use PHPUnit\Framework\TestCase;

class BusinessTest extends PHPUnit\Framework\TestCase
{

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

}
