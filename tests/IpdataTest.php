<?php

declare(strict_types=1);

namespace Ipdata\ApiClient\Tests;

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

    public function testConstructWithDiscovery()
    {
        $ipdata = new Ipdata('secret_key');
        $this->assertInstanceOf(Ipdata::class, $ipdata);
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
                    'is_proxy' => false,
                    'is_anonymous' => false,
                    'is_known_attacker' => false,
                    'is_known_abuser' => false,
                    'is_threat' => false,
                    'is_bogon' => false,
                ],
                'count' => '3',
            ];
        }

        return new Response($statusCode, ['Content-Type' => 'application/json; charset=utf-8'], json_encode($data));
    }
}
