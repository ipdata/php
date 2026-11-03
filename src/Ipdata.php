<?php

declare(strict_types=1);

namespace Ipdata\ApiClient;

use Composer\InstalledVersions;
use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A small client to talk with Ipdata.co API.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Ipdata
{
    public const DEFAULT_BASE_URL = 'https://api.ipdata.co';
    public const EU_BASE_URL = 'https://eu-api.ipdata.co';

    /**
     * Name of this package on Packagist, used to read the installed version for the User-Agent header.
     */
    private const PACKAGE_NAME = 'ipdata/api-client';

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
     * @var string
     */
    private $baseUrl;

    /**
     * @var string|null
     */
    private $httpVersion;

    /**
     * @var string
     */
    private $userAgent;

    /**
     * Get an instance of the API client. Give it an API key, a PSR-18 client and a PSR-17 request factory.
     *
     * The instance keeps the PSR-18 client for its whole lifetime and sends every request through it, so the
     * persistent connections of that client are reused. Create one Ipdata per process and reuse it for every lookup.
     *
     * Pass $httpVersion = '2.0' to make curl based clients that honour the PSR-7 protocol version (Guzzle,
     * php-http/curl-client) negotiate HTTP/2. Leave it null for Symfony HttpClient, which negotiates HTTP/2 on its
     * own, and for stream based clients, which would write "HTTP/2.0" literally into the request line.
     *
     * @param ClientInterface|null         $httpClient     if null, we will try to use php-http/discovery to find an installed client
     * @param RequestFactoryInterface|null $requestFactory if null, we will try to use php-http/discovery to find an installed factory
     * @param string                       $baseUrl        API endpoint: self::DEFAULT_BASE_URL, or self::EU_BASE_URL to use the EU servers
     * @param string|null                  $httpVersion    HTTP protocol version applied to every request, null keeps the request factory's default
     */
    public function __construct(string $apiKey, ?ClientInterface $httpClient = null, ?RequestFactoryInterface $requestFactory = null, string $baseUrl = self::DEFAULT_BASE_URL, ?string $httpVersion = null)
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
        $this->baseUrl = $baseUrl;
        $this->httpVersion = $httpVersion;
        $this->userAgent = self::buildUserAgent();
    }

    /**
     * @param array<string> $fields
     *
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function lookup(string $ip = '', array $fields = []): array
    {
        $query = [
            'api-key' => $this->apiKey,
        ];

        if (!empty($fields)) {
            $query['fields'] = implode(',', $fields);
        }

        $url = '' !== $ip ? sprintf('%s/%s', $this->baseUrl, $ip) : $this->baseUrl;
        $request = $this->createRequest('GET', sprintf('%s?%s', $url, http_build_query($query)));
        $response = $this->httpClient->sendRequest($request);

        return $this->parseResponse($response);
    }

    /**
     * Bulk lookup, requires paid subscription.
     *
     * @param array<string> $ips
     * @param array<string> $fields
     *
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function bulkLookup(array $ips, array $fields = []): array
    {
        $query = [
            'api-key' => $this->apiKey,
        ];

        if (!empty($fields)) {
            $query['fields'] = implode(',', $fields);
        }

        $request = $this->createRequest('POST', sprintf('%s/bulk?%s', $this->baseUrl, http_build_query($query)));
        $request->getBody()->write(json_encode($ips));
        $request = $request->withHeader('Content-Type', 'application/json');
        $response = $this->httpClient->sendRequest($request);

        return $this->parseResponse($response);
    }

    /**
     * @deprecated use bulkLookup() instead
     *
     * @param array<string> $ips
     * @param array<string> $fields
     *
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function buildLookup(array $ips, array $fields = []): array
    {
        return $this->bulkLookup($ips, $fields);
    }

    /**
     * Create the request through the PSR-17 factory, add the User-Agent header and apply the configured HTTP
     * protocol version, if any.
     */
    private function createRequest(string $method, string $url): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $url)->withHeader('User-Agent', $this->userAgent);
        if (null !== $this->httpVersion) {
            $request = $request->withProtocolVersion($this->httpVersion);
        }

        return $request;
    }

    /**
     * Value of the User-Agent header: "ipdata-php/<version>", where the version is the installed version of this
     * package as recorded by Composer 2 in vendor/composer/installed.php. When Composer's runtime API is not
     * available the header is sent as "ipdata-php".
     */
    private static function buildUserAgent(): string
    {
        $version = null;
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
            $version = InstalledVersions::getPrettyVersion(self::PACKAGE_NAME);
        }

        return null === $version ? 'ipdata-php' : 'ipdata-php/'.$version;
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
