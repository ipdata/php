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

## How to use

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
        "is_proxy": false,
        "is_anonymous": false,
        "is_known_attacker": false,
        "is_known_abuser": false,
        "is_threat": false,
        "is_bogon": false
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
$data = $ipdata->buildLookup(['1.1.1.1', '69.78.70.144'], ['longitude', 'latitude', 'country_name']);
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

## Available Fields

A list of all the fields returned by the API is maintained at [Response Fields](https://docs.ipdata.co/api-reference/response-fields)

## Errors

A list of possible errors is available at [Status Codes](https://docs.ipdata.co/api-reference/status-codes)
