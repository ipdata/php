# Ipdata.co API client for PHP

An API client to communicate with Ipdata.co.

## Install

```
composer require ipdata/api-client
```

The client is built upon PSR standards. We use PSR-18 for HTTP client and PSR-17 
for RequestFactory. These needs to be passed to the client's constructor. 

Example packages for PSR-17 and PSR-18:

```
composer require nyholm/psr7 symfony/http-client
```

```php
use Ipdata\ApiClient\Ipdata;
use Symfony\Component\HttpClient\Psr18Client;
use Nyholm\Psr7\Factory\Psr17Factory;

$httpClient = new Psr18Client();
$psr17Factory = new Psr17Factory();
$ipdata = new Ipdata($httpClient, 'my_api_key', $psr17Factory);
```

## Use

To send a geocode request you simply need to provide the IP address you are interested in. 

```php
$data = $ipdata->lookup('69.78.70.144');

```