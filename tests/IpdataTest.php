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
        $ipdata->lookup('103.76.180.54');

        $request = $httpClient->getLastRequest();
        $this->assertEquals('/103.76.180.54', $request->getUri()->getPath());
        $this->assertEquals('api-key=secret_key', $request->getUri()->getQuery());
    }

    public function testOneField()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);
        $ipdata->lookup('103.76.180.54', ['continent_code']);


        $request = $httpClient->getLastRequest();
        $this->assertEquals('/103.76.180.54', $request->getUri()->getPath());
        $this->assertEquals('api-key=secret_key&fields=continent_code', $request->getUri()->getQuery());
    }

    public function testMultipleFields()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);
        $ipdata->lookup('103.76.180.54', ['country_name', 'threat']);


        $request = $httpClient->getLastRequest();
        $this->assertEquals('/103.76.180.54', $request->getUri()->getPath());
        $this->assertEquals('api-key=secret_key&fields=country_name,threat', urldecode($request->getUri()->getQuery()));
    }

    public function testBulkNoFields()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);
        $ipdata->buildLookup(['8.8.8.8', '103.76.180.54']);

        $request = $httpClient->getLastRequest();
        $this->assertEquals('/bulk', $request->getUri()->getPath());
        $this->assertEquals('api-key=secret_key', $request->getUri()->getQuery());
        $this->assertEquals('["8.8.8.8","103.76.180.54"]', $request->getBody()->__toString());
    }

    public function testBulkOneField()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);
        $ipdata->buildLookup(['8.8.8.8', '103.76.180.54'], ['continent_code']);


        $request = $httpClient->getLastRequest();
        $this->assertEquals('/bulk', $request->getUri()->getPath());
        $this->assertEquals('api-key=secret_key&fields=continent_code', $request->getUri()->getQuery());
        $this->assertEquals('["8.8.8.8","103.76.180.54"]', $request->getBody()->__toString());
    }

    public function testBulkMultipleFields()
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse());
        $ipdata = $this->createIpdata($httpClient);
        $ipdata->buildLookup(['8.8.8.8', '103.76.180.54'], ['country_name', 'threat']);


        $request = $httpClient->getLastRequest();
        $this->assertEquals('/bulk', $request->getUri()->getPath());
        $this->assertEquals('api-key=secret_key&fields=country_name,threat', urldecode($request->getUri()->getQuery()));
        $this->assertEquals('["8.8.8.8","103.76.180.54"]', $request->getBody()->__toString());
    }

    /**
     * @dataProvider responseProvider
     */
    public function testStatusInResponse(int $statusCode, array $body)
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($this->createResponse($body, $statusCode));
        $ipdata = $this->createIpdata($httpClient);

        $result = $ipdata->lookup('103.76.180.54');
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals($statusCode, $result['status']);
    }

    public function responseProvider()
    {
        yield '200 response' => [200, ['foo'=>'bar']];
        yield '400 response' => [400, ['message'=>'127.0.0.1 is a private IP address']];
        yield '401 response' => [401, ['message'=>'You have not provided a valid API Key.']];
        yield '403 response' => [403, ['message'=>'You have either exceeded your quota or that API key does not exist...']];
    }

    public function testParseNoneJsonResponse()
    {
        $httpClient = new MockClient();
        $response = new Response(200, ['Content-Type' => 'text/html'], 'Foobar');
        $httpClient->addResponse($response);
        $ipdata = $this->createIpdata($httpClient);

        $this->expectException(\RuntimeException::class);
        $ipdata->lookup('103.76.180.54');
    }

    public function testParseInvalidJsonResponse()
    {
        $httpClient = new MockClient();
        $response = new Response(200, ['Content-Type' => 'application/json'], 'Foobar');
        $httpClient->addResponse($response);
        $ipdata = $this->createIpdata($httpClient);

        $this->expectException(\RuntimeException::class);
        $ipdata->lookup('103.76.180.54');
    }

    public function testConstructWithDiscovery()
    {
        $ipdata = new Ipdata(null, 'secret_key');
        $this->assertInstanceOf(Ipdata::class, $ipdata);
    }

    private function createIpdata(ClientInterface $httpClient): Ipdata
    {
        return new Ipdata($httpClient, 'secret_key', new Psr17Factory());
    }

    private function createResponse(array $data = [], int $statusCode = 200): ResponseInterface
    {
        if (empty($data)) {
            $data = [
                'ip' => '103.76.180.54',
                'is_eu' => false,
                'city' => null,
                'region' => null,
                'region_code' => null,
                'country_name' => 'Thailand',
                'country_code' => 'TH',
                'continent_name' => 'Asia',
                'continent_code' => 'AS',
                'latitude' => 13.7442,
                'longitude' => 100.4608,
                'postal' => null,
                'calling_code' => '66',
                'flag' => 'https://ipdata.co/flags/th.png',
                'emoji_flag' => "\ud83c\uddf9\ud83c\udded",
                'emoji_unicode' => 'U+1F1F9 U+1F1ED',
                'asn' => [
                    'asn' => 'AS23884',
                    'name' => 'Proimage Engineering and Communication Co.,Ltd.',
                    'domain' => 'proen.co.th',
                    'route' => '103.76.180.0/22',
                    'type' => 'hosting',
                ],
                'languages' => [['name' => 'Thai', 'native' => "\u0e44\u0e17\u0e22 / Phasa Thai"]],
                'currency' => [
                    'name' => 'Thai Baht',
                    'code' => 'THB',
                    'symbol' => "\u0e3f",
                    'native' => "\u0e3f",
                    'plural' => 'Thai baht',
                ],
                'time_zone' => [
                    'name' => 'Asia/Bangkok',
                    'abbr' => '+07',
                    'offset' => '+0700',
                    'is_dst' => false,
                    'current_time' => '2020-01-25T18:38:28.142867+07:00',
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
                'count' => '0',
            ];
        }

        return new Response($statusCode, ['Content-Type' => 'application/json; charset=utf-8'], json_encode($data));
    }
}
