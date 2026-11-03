# Change Log

## 0.1.3

### Added

- Opt-in HTTP protocol version: the constructor takes a trailing `?string $httpVersion` (for example `'2.0'`) that is applied to every request with `withProtocolVersion()`, so curl based PSR-18 clients such as Guzzle and php-http/curl-client negotiate HTTP/2. `null` (the default) leaves the version to the request factory.
- README section "Connection reuse and HTTP/2": reuse one `Ipdata` and one PSR-18 client per process to keep the HTTPS connection alive.
- Documentation and tests for the new API response fields: top-level `is_satellite` and `is_anycast` (present only when `true`) and `first_seen`, `last_seen` (`YYYY-MM-DD`, UTC) and `days_seen` on `threat.blocklists` entries (absent for some feeds). The client passes them through unchanged.

### Fixed

- The `$httpClient` and `$requestFactory` constructor parameters are declared explicitly nullable (`?ClientInterface`, `?RequestFactoryInterface`); PHP 8.4 deprecates implicitly nullable parameters.

## 0.1.2

### Added

- `lookup()` without arguments looks up the IP address of the caller.
- `Ipdata::EU_BASE_URL` and a trailing `$baseUrl` constructor argument to route requests through ipdata's EU endpoint (eu-api.ipdata.co).
- `bulkLookup()`; `buildLookup()` is kept as a deprecated alias.

### Fixed

- Bulk requests are sent with `Content-Type: application/json` instead of `text/plain`.

### Removed

- Unused dev dependencies `php-http/httplug` and `symfony/http-client`.

## 0.1.1

### Fixed

- PHP8 support

## 0.1.0

Init release