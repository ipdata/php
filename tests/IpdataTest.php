<?php

declare(strict_types=1);

namespace Ipdata\ApiClient\Tests;

use Composer\InstalledVersions;
use Http\Mock\Client as MockClient;
use Ipdata\ApiClient\Ipdata;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

class IpdataTest extends TestCase
{
    public function testNoFields()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);
        $ipdata->lookup('69.78.70.144');

        $request = $httpClient->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/69.78.70.144', $request->getUri()->getPath());
        $this->assertEquals('api-key=secret_key', $request->getUri()->getQuery());
    }

    public function testOneField()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);
        $ipdata->lookup('69.78.70.144', ['continent_code']);

        $request = $httpClient->getLastRequest();
        $this->assertEquals('/69.78.70.144', $request->getUri()->getPath());
        $this->assertEquals('api-key=secret_key&fields=continent_code', $request->getUri()->getQuery());
    }

    public function testMultipleFields()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);
        $ipdata->lookup('69.78.70.144', ['country_name', 'threat']);

        $request = $httpClient->getLastRequest();
        $this->assertEquals('/69.78.70.144', $request->getUri()->getPath());
        $this->assertEquals('api-key=secret_key&fields=country_name,threat', urldecode($request->getUri()->getQuery()));
    }

    public function testBulkNoFields()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);
        $ipdata->buildLookup(['8.8.8.8', '69.78.70.144']);

        $request = $httpClient->getLastRequest();
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/bulk', $request->getUri()->getPath());
        $this->assertEquals('api-key=secret_key', $request->getUri()->getQuery());
        $this->assertEquals('["8.8.8.8","69.78.70.144"]', $request->getBody()->__toString());
    }

    public function testBulkOneField()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);
        $ipdata->buildLookup(['8.8.8.8', '69.78.70.144'], ['continent_code']);

        $request = $httpClient->getLastRequest();
        $this->assertEquals('/bulk', $request->getUri()->getPath());
        $this->assertEquals('api-key=secret_key&fields=continent_code', $request->getUri()->getQuery());
        $this->assertEquals('["8.8.8.8","69.78.70.144"]', $request->getBody()->__toString());
    }

    public function testBulkMultipleFields()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);
        $ipdata->buildLookup(['8.8.8.8', '69.78.70.144'], ['country_name', 'threat']);

        $request = $httpClient->getLastRequest();
        $this->assertEquals('/bulk', $request->getUri()->getPath());
        $this->assertEquals('api-key=secret_key&fields=country_name,threat', urldecode($request->getUri()->getQuery()));
        $this->assertEquals('["8.8.8.8","69.78.70.144"]', $request->getBody()->__toString());
    }

    /**
     * @dataProvider responseProvider
     */
    public function testStatusInResponse(int $statusCode, array $body)
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse($body, $statusCode));
        $ipdata = $this->createIpdata($httpClient);

        $result = $ipdata->lookup('69.78.70.144');
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals($statusCode, $result['status']);
    }

    public function responseProvider()
    {
        yield '200 response' => [200, ['foo' => 'bar']];
        yield '400 response' => [400, ['message' => '127.0.0.1 is a private IP address']];
        yield '401 response' => [401, ['message' => 'You have not provided a valid API Key.']];
        yield '403 response' => [403, ['message' => 'You have either exceeded your quota or that API key does not exist...']];
    }

    public function testParseNoneJsonResponse()
    {
        $httpClient = new MockClient();
        $response = new Response(200, ['Content-Type' => 'text/html'], 'Foobar');
        $httpClient->addResponse($response);
        $ipdata = $this->createIpdata($httpClient);

        $this->expectException(\RuntimeException::class);
        $ipdata->lookup('69.78.70.144');
    }

    public function testParseInvalidJsonResponse()
    {
        $httpClient = new MockClient();
        $response = new Response(200, ['Content-Type' => 'application/json'], 'Foobar');
        $httpClient->addResponse($response);
        $ipdata = $this->createIpdata($httpClient);

        $this->expectException(\RuntimeException::class);
        $ipdata->lookup('69.78.70.144');
    }

    public function testCallerIpLookup()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);
        $ipdata->lookup();

        $request = $httpClient->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('', $request->getUri()->getPath());
        $this->assertEquals('api-key=secret_key', $request->getUri()->getQuery());
    }

    public function testBulkLookup()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);
        $ipdata->bulkLookup(['8.8.8.8', '69.78.70.144']);

        $request = $httpClient->getLastRequest();
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/bulk', $request->getUri()->getPath());
        $this->assertEquals('api-key=secret_key', $request->getUri()->getQuery());
        $this->assertEquals('["8.8.8.8","69.78.70.144"]', $request->getBody()->__toString());
    }

    public function testBulkLookupContentType()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);
        $ipdata->bulkLookup(['8.8.8.8']);

        $request = $httpClient->getLastRequest();
        $this->assertEquals('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function testCustomBaseUrl()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = new Ipdata('secret_key', $httpClient, new Psr17Factory(), Ipdata::EU_BASE_URL);
        $ipdata->lookup('8.8.8.8');

        $request = $httpClient->getLastRequest();
        $this->assertEquals('eu-api.ipdata.co', $request->getUri()->getHost());
    }

    public function testConstructWithDiscoveryThrowsWhenNoClientAvailable()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Could not find any installed HTTP clients');
        new Ipdata('secret_key');
    }

    public function testHttpVersionIsAppliedToLookupRequests()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = new Ipdata('secret_key', $httpClient, new Psr17Factory(), 'https://api.ipdata.co', '2.0');
        $ipdata->lookup('8.8.8.8');

        $request = $httpClient->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/8.8.8.8', $request->getUri()->getPath());
        $this->assertEquals('2.0', $request->getProtocolVersion());
    }

    public function testHttpVersionIsAppliedToBulkLookupRequests()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = new Ipdata('secret_key', $httpClient, new Psr17Factory(), 'https://api.ipdata.co', '2.0');
        $ipdata->bulkLookup(['8.8.8.8', '69.78.70.144']);

        $request = $httpClient->getLastRequest();
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/bulk', $request->getUri()->getPath());
        $this->assertEquals('2.0', $request->getProtocolVersion());
        $this->assertEquals('["8.8.8.8","69.78.70.144"]', $request->getBody()->__toString());
    }

    public function testHttpVersionIsLeftToTheRequestFactoryWhenNull()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $httpClient->addResponse($this->createResponse());
        $ipdata = new Ipdata('secret_key', $httpClient, new Psr17Factory(), 'https://api.ipdata.co', null);
        $ipdata->lookup('8.8.8.8');
        $ipdata->bulkLookup(['8.8.8.8']);

        $requests = $httpClient->getRequests();
        $this->assertCount(2, $requests);
        // nyholm/psr7 creates HTTP/1.1 requests; without a configured version the client must not change that
        $this->assertEquals('1.1', $requests[0]->getProtocolVersion());
        $this->assertEquals('1.1', $requests[1]->getProtocolVersion());
    }

    public function testConsecutiveCallsAreSentThroughTheSameClient()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $httpClient->addResponse($this->createResponse());
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);

        $ipdata->lookup('8.8.8.8');
        $ipdata->lookup();
        $ipdata->bulkLookup(['8.8.8.8', '69.78.70.144']);

        $requests = $httpClient->getRequests();
        $this->assertCount(3, $requests);
        $this->assertEquals('/8.8.8.8', $requests[0]->getUri()->getPath());
        $this->assertEquals('', $requests[1]->getUri()->getPath());
        $this->assertEquals('/bulk', $requests[2]->getUri()->getPath());
    }

    public function testExplicitNullRequestFactoryFallsBackToDiscovery()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = new Ipdata('secret_key', $httpClient, null);
        $result = $ipdata->lookup('8.8.8.8');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals('/8.8.8.8', $httpClient->getLastRequest()->getUri()->getPath());
    }

    public function testSatelliteFlagIsPassedThrough()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createFixtureResponse('lookup_satellite'));
        $ipdata = $this->createIpdata($httpClient);

        $result = $ipdata->lookup('98.97.34.0');

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['is_satellite']);
        $this->assertFalse($result['is_eu']);
        // the API includes is_satellite and is_anycast only when they are true, so the key is absent here
        $this->assertArrayNotHasKey('is_anycast', $result);
        $this->assertFalse($result['is_anycast'] ?? false);
        $this->assertSame('AS14593', $result['asn']['asn']);
        $this->assertSame([], $result['threat']['blocklists']);
    }

    public function testAnycastFlagAndDatedBlocklistEntriesArePassedThrough()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createFixtureResponse('lookup_anycast'));
        $ipdata = $this->createIpdata($httpClient);

        $result = $ipdata->lookup('8.8.8.8');

        $this->assertTrue($result['is_anycast']);
        $this->assertArrayNotHasKey('is_satellite', $result);
        $this->assertFalse($result['is_satellite'] ?? false);
        $this->assertNull($result['city']);
        $this->assertTrue($result['threat']['is_datacenter']);

        $blocklists = $result['threat']['blocklists'];
        $this->assertCount(2, $blocklists);
        $this->assertSame([
            'days_seen' => 1,
            'first_seen' => '2026-11-02',
            'last_seen' => '2026-11-02',
            'name' => 'ipdata',
            'site' => 'https://ipdata.co',
            'type' => 'cloud',
        ], $blocklists[0]);
        $this->assertSame('open_resolvers', $blocklists[1]['type']);
        // the dates stay plain YYYY-MM-DD strings and days_seen stays an integer
        $this->assertSame('2026-11-02', $blocklists[1]['first_seen']);
        $this->assertSame('2026-11-02', $blocklists[1]['last_seen']);
        $this->assertSame(1, $blocklists[1]['days_seen']);
    }

    public function testBlocklistEntriesWithoutDatesArePassedThrough()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createFixtureResponse('lookup_vpn'));
        $ipdata = $this->createIpdata($httpClient);

        $result = $ipdata->lookup('187.13.250.1');

        // neither flag is present for this address
        $this->assertArrayNotHasKey('is_satellite', $result);
        $this->assertArrayNotHasKey('is_anycast', $result);
        $this->assertTrue($result['threat']['is_vpn']);

        $blocklists = $result['threat']['blocklists'];
        $this->assertCount(6, $blocklists);
        $this->assertSame(2, $blocklists[0]['days_seen']);
        $this->assertSame('2026-10-31', $blocklists[0]['first_seen']);
        $this->assertSame('2026-11-02', $blocklists[0]['last_seen']);
        // some feeds, for example ipdata's own vpn feed, carry none of the three date fields
        $this->assertSame(['name' => 'ipdata', 'site' => 'https://ipdata.co', 'type' => 'vpn'], $blocklists[2]);
        $this->assertArrayNotHasKey('first_seen', $blocklists[2]);
        $this->assertArrayNotHasKey('last_seen', $blocklists[2]);
        $this->assertArrayNotHasKey('days_seen', $blocklists[2]);
        $this->assertNull($blocklists[2]['first_seen'] ?? null);
    }

    public function testBulkLookupItemsCarryTheNewFields()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse([
            $this->loadFixture('lookup_satellite'),
            $this->loadFixture('lookup_anycast'),
            $this->loadFixture('lookup_vpn'),
        ]));
        $ipdata = $this->createIpdata($httpClient);

        $result = $ipdata->bulkLookup(['98.97.34.0', '8.8.8.8', '187.13.250.1']);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result[0]['is_satellite']);
        $this->assertArrayNotHasKey('is_anycast', $result[0]);
        $this->assertTrue($result[1]['is_anycast']);
        $this->assertArrayNotHasKey('is_satellite', $result[1]);
        $this->assertSame('2026-11-02', $result[1]['threat']['blocklists'][0]['first_seen']);
        $this->assertSame(1, $result[1]['threat']['blocklists'][0]['days_seen']);
        $this->assertArrayNotHasKey('is_satellite', $result[2]);
        $this->assertArrayNotHasKey('is_anycast', $result[2]);
        $this->assertArrayNotHasKey('first_seen', $result[2]['threat']['blocklists'][2]);
    }

    public function testNewFieldNamesCanBeRequested()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);

        $ipdata->lookup('8.8.8.8', ['is_satellite', 'is_anycast', 'threat']);
        $ipdata->bulkLookup(['8.8.8.8'], ['is_satellite', 'is_anycast']);

        $requests = $httpClient->getRequests();
        $this->assertEquals('api-key=secret_key&fields=is_satellite,is_anycast,threat', urldecode($requests[0]->getUri()->getQuery()));
        $this->assertEquals('api-key=secret_key&fields=is_satellite,is_anycast', urldecode($requests[1]->getUri()->getQuery()));
    }

    public function testDefaultBaseUrlConstantIsPublic()
    {
        $this->assertSame('https://api.ipdata.co', Ipdata::DEFAULT_BASE_URL);
        $this->assertTrue((new \ReflectionClassConstant(Ipdata::class, 'DEFAULT_BASE_URL'))->isPublic());

        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        // the constant lets callers pass the trailing $httpVersion without repeating the default endpoint
        $ipdata = new Ipdata('secret_key', $httpClient, new Psr17Factory(), Ipdata::DEFAULT_BASE_URL, '2.0');
        $ipdata->lookup('8.8.8.8');

        $request = $httpClient->getLastRequest();
        $this->assertSame('api.ipdata.co', $request->getUri()->getHost());
        $this->assertSame('/8.8.8.8', $request->getUri()->getPath());
        $this->assertSame('2.0', $request->getProtocolVersion());
    }

    public function testUserAgentHeaderIsSentWithEveryRequest()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);

        $ipdata->lookup('8.8.8.8');
        $ipdata->bulkLookup(['8.8.8.8']);

        $requests = $httpClient->getRequests();
        $this->assertCount(2, $requests);
        // the version is the one Composer recorded for this package in vendor/composer/installed.php
        $expected = 'ipdata-php/'.InstalledVersions::getPrettyVersion('ipdata/api-client');
        $this->assertRegExp('#^ipdata-php/\S+$#', $expected);
        $this->assertSame($expected, $requests[0]->getHeaderLine('User-Agent'));
        $this->assertSame($expected, $requests[1]->getHeaderLine('User-Agent'));
        // the bulk request keeps its Content-Type next to the User-Agent header
        $this->assertSame('application/json', $requests[1]->getHeaderLine('Content-Type'));
    }

    private function createIpdata(ClientInterface $httpClient): Ipdata
    {
        return new Ipdata('secret_key', $httpClient, new Psr17Factory());
    }

    private function createResponse(array $data = [], int $statusCode = 200): ResponseInterface
    {
        if (empty($data)) {
            $data = [
                'ip' => '69.78.70.144',
                'is_eu' => false,
                'city' => null,
                'region' => null,
                'region_code' => null,
                'country_name' => 'United States',
                'country_code' => 'US',
                'continent_name' => 'North America',
                'continent_code' => 'NA',
                'latitude' => 37.751,
                'longitude' => -97.822,
                'postal' => null,
                'calling_code' => '1',
                'flag' => 'https://ipdata.co/flags/us.png',
                'emoji_flag' => "\ud83c\uddfa\ud83c\uddf8",
                'emoji_unicode' => 'U+1F1FA U+1F1F8',
                'company' => [
                    'name' => 'Verizon Wireless',
                    'domain' => 'verizonwireless.com',
                    'network' => '69.78.0.0/16',
                    'type' => 'isp',
                ],
                'asn' => [
                    'asn' => 'AS6167',
                    'name' => 'Cellco Partnership DBA Verizon Wireless',
                    'domain' => 'verizonwireless.com',
                    'route' => '69.78.0.0/16',
                    'type' => 'business',
                ],
                'carrier' => [
                    'name' => 'Verizon',
                    'mcc' => '310',
                    'mnc' => '004',
                ],
                'languages' => [
                    ['name' => 'English', 'native' => 'English'],
                ],
                'currency' => [
                    'name' => 'US Dollar',
                    'code' => 'USD',
                    'symbol' => '$',
                    'native' => '$',
                    'plural' => 'US dollars',
                ],
                'time_zone' => [
                    'name' => 'America/Chicago',
                    'abbr' => 'CST',
                    'offset' => '-0600',
                    'is_dst' => false,
                    'current_time' => '2020-01-25T06:11:31.771651-06:00',
                ],
                'threat' => [
                    'is_tor' => false,
                    'is_vpn' => false,
                    'is_icloud_relay' => false,
                    'is_proxy' => false,
                    'is_datacenter' => false,
                    'is_anonymous' => false,
                    'is_known_attacker' => false,
                    'is_known_abuser' => false,
                    'is_threat' => false,
                    'is_bogon' => false,
                    'blocklists' => [],
                    'scores' => [
                        'vpn_score' => 0,
                        'proxy_score' => 0,
                        'threat_score' => 0,
                        'trust_score' => 0,
                    ],
                ],
                'count' => '3',
            ];
        }

        return new Response($statusCode, ['Content-Type' => 'application/json; charset=utf-8'], json_encode($data));
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__.'/Fixtures/'.$name.'.json'), true);
    }

    private function createFixtureResponse(string $name, int $statusCode = 200): ResponseInterface
    {
        return new Response($statusCode, ['Content-Type' => 'application/json; charset=utf-8'], file_get_contents(__DIR__.'/Fixtures/'.$name.'.json'));
    }
}
