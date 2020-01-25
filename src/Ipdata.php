<?php

declare(strict_types=1);

namespace Ipdata\ApiClient;

use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A small client to talk with Ipdata.co API.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Ipdata
{
    private const BASE_URL = 'https://api.ipdata.co';

    /**
     * @var string|null
     */
    private $apiKey;

    /**
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * Get an instance of the API client. Give it an API key, a PSR-18 client and a PSR-17 request factory.
     *
     * @param ClientInterface|null         $httpClient     if null, we will try to use php-http/discovery to find an installed client
     * @param RequestFactoryInterface|null $requestFactory if null, we will try to use php-http/discovery to find an installed factory
     */
    public function __construct(string $apiKey, ClientInterface $httpClient = null, RequestFactoryInterface $requestFactory = null)
    {
        if (null === $httpClient) {
            if (!class_exists(Psr18ClientDiscovery::class)) {
                throw new \LogicException(sprintf('You cannot use the "%s" without a PSR-18 HTTP client. Pass a "%s" as first argument to the constructor OR try running  "composer require php-http/discovery".', Ipdata::class, ClientInterface::class));
            }

            try {
                $httpClient = Psr18ClientDiscovery::find();
            } catch (NotFoundException $e) {
                throw new \LogicException('Could not find any installed HTTP clients. Try installing a package for this list: https://packagist.org/providers/psr/http-client-implementation', 0, $e);
            }
        }

        if (null === $requestFactory) {
            if (!class_exists(Psr17Factory::class) && !class_exists(Psr17FactoryDiscovery::class)) {
                throw new \LogicException(sprintf('You cannot use the "%s" as no PSR-17 request factory have been provided. Try running "composer require nyholm/psr7".', Ipdata::class));
            }

            try {
                $requestFactory = class_exists(Psr17Factory::class) ? new Psr17Factory() : Psr17FactoryDiscovery::findRequestFactory();
            } catch (NotFoundException $e) {
                throw new \LogicException('Could not find any PSR-17 factories. Try running "composer require nyholm/psr7".', 0, $e);
            }
        }

        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
        $this->requestFactory = $requestFactory;
    }

    /**
     * @param array<string> $fields
     *
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function lookup(string $ip, array $fields = []): array
    {
        $query = [
            'api-key' => $this->apiKey,
        ];

        if (!empty($fields)) {
            $query['fields'] = implode(',', $fields);
        }

        $request = $this->requestFactory->createRequest('GET', sprintf('%s/%s?%s', self::BASE_URL, $ip, http_build_query($query)));
        $response = $this->httpClient->sendRequest($request);

        return $this->parseResponse($response);
    }

    /**
     * Bulk lookup, requires paid subscription.
     *
     * @param array<string> $fields
     *
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function buildLookup(array $ips, array $fields = []): array
    {
        $query = [
            'api-key' => $this->apiKey,
        ];

        if (!empty($fields)) {
            $query['fields'] = implode(',', $fields);
        }

        $request = $this->requestFactory->createRequest('POST', sprintf('%s/bulk?%s', self::BASE_URL, http_build_query($query)));
        $request->getBody()->write(json_encode($ips));
        $request = $request->withAddedHeader('Content-Type', 'text/plain');
        $response = $this->httpClient->sendRequest($request);

        return $this->parseResponse($response);
    }

    private function parseResponse(ResponseInterface $response): array
    {
        $body = $response->getBody()->__toString();
        if (0 !== strpos($response->getHeaderLine('Content-Type'), 'application/json')) {
            throw new \RuntimeException('Cannot convert response to array. Response has Content-Type:'.$response->getHeaderLine('Content-Type'));
        }

        $content = json_decode($body, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \RuntimeException(sprintf('Error (%d) when trying to json_decode response', json_last_error()));
        }

        $content['status'] = $response->getStatusCode();

        return $content;
    }
}
