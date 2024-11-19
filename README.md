# aternos/php-curl-psr

A simple PSR-18 client implementation based on cURL that actually supports
streaming both requests and responses.

## Installation

```bash
composer require aternos/curl-psr
```

In addition to [PSR-18 (HTTP Client)](https://www.php-fig.org/psr/psr-18/), this library also provides
implementations for [PSR-17 (HTTP Factories)](https://www.php-fig.org/psr/psr-17/) and
[PSR-7 (HTTP Messages)](https://www.php-fig.org/psr/psr-7/), so no other implementations need to be installed.

## Usage

### Creating a client

```php
$client = new \Aternos\CurlPsr\Psr18\Client();
```

When creating a client, you can optionally provide a PSR-17 `ResponseFactoryInterface` instance. By default,
the client will use the `Aternos\CurlPsr\Psr17\Psr17Factory` class included in this library.

Additionally, you can pass an optional `CurlHandleFactoryInterface` instance as the second argument,
which is mainly used for testing purposes.

### Configuring the client

Since PSR-7 does not offer many request options, you can set client-wide options that are used for all requests.
Requests will use the client options as they are at the moment they are sent.
Changing client options will therefore not affect already running requests.

```php
$client->setTimeout(10) // Set the timeout to 10 seconds
       ->setMaxRedirects(5) // Set the maximum number of redirects to follow to 5
       ->setCookieFile("/path/to/cookie/file") // Set the path to the cURL cookie file 
       ->setCurlOption(CURLOPT_DNS_SHUFFLE_ADDRESSES, true) // Set a custom cURL option
       ->setDefaultHeaders(["User-Agent" => ["MyClient/1.0"]]) // Set default headers for all requests
       ->addDefaultHeader("Accept", "application/json"); // Add a default header

$client->setProgressCallback(function (
    \Psr\Http\Message\RequestInterface $request, 
    int $downloadTotal, 
    int $downloaded, 
    int $uploadTotal, 
    int $uploaded
) {
    // Progress callback
});
```

#### Progress callback
The progress callback function works the same way as the `CURLOPT_PROGRESSFUNCTION` in cURL,
except that it receives the PSR-7 request object instead of a cURL handle as the first argument.
Please note that the request object passed to the callback is not necessarily same instance that was
originally passed to the `sendRequest` method. This is because PSR-7 request objects are immutable,
so the client will create a new request object if changes are necessary (e.g. to add default headers).

### Sending a request

```php
$factory = new \Aternos\CurlPsr\Psr17\Psr17Factory();

$request = $factory->createRequest("GET", "https://example.com")
    ->withHeader("X-Some-Header", "Some Value");
    ->withBody($streamFactory->createStream("Some body"));

$response = $client->sendRequest($request);

$headers = $response->getHeaders();
$stream = $response->getBody();

echo $stream->getContents();
```

CurlPsr can send any PSR-7 request object and return a PSR-7 response object. For more information on how to use PSR-7 objects,
see the [PSR-7 documentation](https://www.php-fig.org/psr/psr-7/).
