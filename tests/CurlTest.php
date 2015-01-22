<?php

require_once __DIR__ . '/../Curl.php';

/**
 * Curl Test
 * Tests Missing include:
 *    - Set HTTP headers tests
 *    - HTTP methods tests
 *    - Packet inspection tests, ... any
 *    - XML post processor tests
 *    - Different request content body types
 */

class CurlTest extends PHPUnit_Framework_TestCase
{
    public function testSimpleInteraction()
    {
        $c = new Curl;
        $r = $c->get('http://www.google.com');
        $this->assertInstanceOf('CurlResponse', $r);
        $this->assertSame($r->status, $c::ResponseSuccess);
    }
    
    public function testNotFound()
    {
        $c = new Curl;
        $r = $c->get('http://www.google.com/lkfsjldkfsj');
        $this->assertSame($r->status, $c::ResponseApplicationError);
    }
    
    // This test is not strictly critical, but it's a cool test to run.
    public function testLiveJsonPostProcessor()
    {
        $c = new Curl;
        $r = $c->get('https://status.github.com/api/status.json');
        $json_huh = $r->body;
        $json_arr = (array) $json_huh;
        
        $this->assertInstanceOf('stdclass', $json_huh);
        $this->assertArrayHasKey('status', $json_arr);
    }
    
    public function testMockInjector()
    {
        $cr = new CurlResponse;
        $cr->setBody('{"a":"123"}', 'application/json');
        Curl::createMockInteraction('https://www.google.com/superpowers', $cr);

        $c = new Curl;
        $r = $c->get('https://www.google.com/superpowers')->body;
        $this->assertInstanceOf('stdclass', $r);
        $this->assertSame($r->a, '123');
    }
}
