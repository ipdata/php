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
$ipdata = new Ipdata('my_api_key', $httpClient, $psr17Factory);
```

### EU endpoint

To route requests through ipdata's EU servers (Paris, Ireland and Frankfurt) for GDPR compliance, pass the EU base URL:

```php
$ipdata = new Ipdata('my_api_key', $httpClient, $psr17Factory, Ipdata::EU_BASE_URL);
```

## How to use

### Look up your own IP

Call `lookup()` with no arguments to get the location of the calling IP address.

```php
$data = $ipdata->lookup();
```

### Look up a specific IP

To send a geocode request you simply need to provide the IP address you are interested in.

```php
$data = $ipdata->lookup('69.78.70.144');
echo json_encode($data, JSON_PRETTY_PRINT);
```

The output will be the response from the API server with one additional `status` field. 

```json
{
    "ip": "69.78.70.144",
    "is_eu": false,
    "city": null,
    "region": null,
    "region_code": null,
    "country_name": "United States",
    "country_code": "US",
    "continent_name": "North America",
    "continent_code": "NA",
    "latitude": 37.751,
    "longitude": -97.822,
    "postal": null,
    "calling_code": "1",
    "flag": "https:\/\/ipdata.co\/flags\/us.png",
    "emoji_flag": "\ud83c\uddfa\ud83c\uddf8",
    "emoji_unicode": "U+1F1FA U+1F1F8",
    "company": {
        "name": "Verizon Wireless",
        "domain": "verizonwireless.com",
        "network": "69.78.0.0\/16",
        "type": "isp"
    },
    "asn": {
        "asn": "AS6167",
        "name": "Cellco Partnership DBA Verizon Wireless",
        "domain": "verizonwireless.com",
        "route": "69.78.0.0\/16",
        "type": "business"
    },
    "carrier": {
        "name": "Verizon",
        "mcc": "310",
        "mnc": "004"
    },
    "languages": [
        {
            "name": "English",
            "native": "English"
        }
    ],
    "currency": {
        "name": "US Dollar",
        "code": "USD",
        "symbol": "$",
        "native": "$",
        "plural": "US dollars"
    },
    "time_zone": {
        "name": "America\/Chicago",
        "abbr": "CST",
        "offset": "-0600",
        "is_dst": false,
        "current_time": "2020-01-25T06:14:37.081772-06:00"
    },
    "threat": {
        "is_tor": false,
        "is_vpn": false,
        "is_icloud_relay": false,
        "is_proxy": false,
        "is_datacenter": false,
        "is_anonymous": false,
        "is_known_attacker": false,
        "is_known_abuser": false,
        "is_threat": false,
        "is_bogon": false,
        "blocklists": [],
        "scores": {
            "vpn_score": 0,
            "proxy_score": 0,
            "threat_score": 0,
            "trust_score": 0
        }
    },
    "count": "6",
    "status": 200
}
```

If you are not interested in all the fields in the response, you may query only
the fields you want. 

```php
$data = $ipdata->lookup('69.78.70.144', ['longitude', 'latitude', 'country_name']);
echo json_encode($data, JSON_PRETTY_PRINT);
```

```json
{
    "longitude": -97.822,
    "latitude": 37.751,
    "country_name": "United States",
    "status": 200
}
```

### Bulk request

If you want to look up multiple IPs at the same time you may use the `bulkLookup()` function.


```php
$data = $ipdata->bulkLookup(['1.1.1.1', '69.78.70.144'], ['longitude', 'latitude', 'country_name']);
echo json_encode($data, JSON_PRETTY_PRINT);
```

```json
{
    "0": {
        "longitude": 143.2104,
        "latitude": -33.494,
        "country_name": "Australia"
    },
    "1": {
        "longitude": -97.822,
        "latitude": 37.751,
        "country_name": "United States"
    },
    "status": 200
}
```

## Connection reuse and HTTP/2

An `Ipdata` instance keeps the PSR-18 client you give it for as long as it lives and sends every request through
it, so the persistent HTTPS connections of that HTTP client are reused across lookups. Create one `Ipdata` per
process (or one per API key or endpoint), register it as a service in your container, and reuse it for every
lookup: each new client (and each new HTTP client) pays a fresh TLS handshake, which costs more than the lookup
itself. Requests to api.ipdata.co are multiplexed over HTTP/2 when the platform negotiates it, and fall back to
HTTP/1.1 keep-alive otherwise.

The library is transport-agnostic, so "the platform" is the PSR-18 client you inject:

* **Symfony HttpClient with ext-curl** (`Symfony\Component\HttpClient\Psr18Client`, used in the examples above):
  nothing to configure. One `Psr18Client` keeps its connections alive and negotiates HTTP/2 through ALPN on its
  own. By default it also ignores the protocol version of the PSR-7 request, so leave `$httpVersion` at `null`.
* **Guzzle and php-http/curl-client** honour the protocol version of the PSR-7 request. Opt in to HTTP/2 by
  passing `'2.0'` as the trailing `$httpVersion` constructor argument; curl then negotiates HTTP/2 through ALPN
  and falls back to HTTP/1.1 when the server does not offer it:

  ```php
  use GuzzleHttp\Client;
  use Ipdata\ApiClient\Ipdata;
  use Nyholm\Psr7\Factory\Psr17Factory;

  $ipdata = new Ipdata('my_api_key', new Client(), new Psr17Factory(), 'https://api.ipdata.co', '2.0');
  ```

  On PHP 8 you can skip the base URL with a named argument: `new Ipdata('my_api_key', new Client(), new Psr17Factory(), httpVersion: '2.0')`.
  Without the argument the request keeps the version set by your PSR-17 factory (HTTP/1.1 with `nyholm/psr7`),
  and the connection is still kept alive between calls on the same instance.
* **Stream-based clients** (Guzzle without ext-curl, php-http/socket-client, anything built on PHP's `http://`
  stream wrapper) must keep `$httpVersion` at `null`: they write the version literally into the request line,
  and a request line reading `HTTP/2.0` breaks the request.

A few things to keep in mind:

* Under PHP-FPM or mod_php, PHP destroys all objects, and with them the curl handles, at the end of each PHP
  request, so a connection is only reused by the lookups made within one PHP request. Long-running workers
  (Symfony Messenger consumers, queue workers, RoadRunner, FrankenPHP worker mode, Swoole, CLI daemons) that keep
  the same `Ipdata` instance benefit fully.
* The PSR-18 API is synchronous, one request at a time, so HTTP/2 multiplexing brings nothing here: the saving is
  the TLS handshake on every call after the first.
* PHP runs one request per process, so there is no thread-safety concern. After `pcntl_fork()`, build a new
  `Ipdata` and HTTP client in the child instead of sharing the parent's sockets.
* `Ipdata` has no `close()` method because it does not own the connections: they belong to the PSR-18 client you
  injected and are closed when that client is released, at the latest when the process ends. Timeouts, proxies
  and other transport settings are configured on that client as well.

## Available Fields

A list of all the fields returned by the API is maintained at [Response Fields](https://docs.ipdata.co/api-reference/response-fields)

The client returns the decoded JSON unchanged, so new fields reach you as soon as the API sends them. Recent additions:

- `is_satellite` (boolean): the IP address belongs to a satellite internet provider. Present only when `true`; the key is omitted otherwise, so read it as `$data['is_satellite'] ?? false`.
- `is_anycast` (boolean): the IP address is an anycast address announced from several locations. Present only when `true`; read it as `$data['is_anycast'] ?? false`.
- `threat.blocklists[].first_seen` (string, `YYYY-MM-DD`, UTC): the first day the address appeared on that blocklist.
- `threat.blocklists[].last_seen` (string, `YYYY-MM-DD`, UTC): the most recent day the address appeared on that blocklist.
- `threat.blocklists[].days_seen` (integer): the number of days the address has been seen on that blocklist.

Some feeds, for example ipdata's own vpn feed, carry none of the three blocklist date fields, so read them as `$entry['first_seen'] ?? null`. The dates are passed through as strings; the client does not parse them. `is_satellite` and `is_anycast` can also be requested through the `$fields` argument of `lookup()` and `bulkLookup()`.

## Errors

A list of possible errors is available at [Status Codes](https://docs.ipdata.co/api-reference/status-codes)
