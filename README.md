# clue/soap-react [![Build Status](https://travis-ci.org/clue/php-soap-react.svg?branch=master)](https://travis-ci.org/clue/php-soap-react)

Simple, async [SOAP](http://en.wikipedia.org/wiki/SOAP) web service client library,
built on top of [ReactPHP](https://reactphp.org/).

Most notably, SOAP is often used for invoking
[Remote procedure calls](http://en.wikipedia.org/wiki/Remote_procedure_call) (RPCs)
in distributed systems.
Internally, SOAP messages are encoded as XML and usually sent via HTTP POST requests.
For the most part, SOAP (originally *Simple Object Access protocol*) is a protocol of the past,
and in fact anything but *simple*.
It is still in use by many (often *legacy*) systems.
This project provides a *simple* API for invoking *async* RPCs to remote web services.

* **Async execution of functions** -
  Send any number of functions (RPCs) to the remote web service in parallel and
  process their responses as soon as results come in.
  The Promise-based design provides a *sane* interface to working with out of bound responses.
* **Async processing of the WSDL** -
  The WSDL (web service description language) file will be downloaded and processed
  in the background.
* **Event-driven core** -
  Internally, everything uses event handlers to react to incoming events, such as an incoming RPC result.
* **Lightweight, SOLID design** -
  Provides a thin abstraction that is [*just good enough*](http://en.wikipedia.org/wiki/Principle_of_good_enough)
  and does not get in your way.
  Built on top of tested components instead of re-inventing the wheel.
* **Good test coverage** -
  Comes with an automated tests suite and is regularly tested against actual web services in the wild

**Table of contents**

* [Quickstart example](#quickstart-example)
* [Usage](#usage)
  * [Factory](#factory)
    * [createClient()](#createclient)
    * [createClientFromWsdl()](#createclientfromwsdl)
  * [Client](#client)
    * [soapCall()](#soapcall)
    * [getFunctions()](#getfunctions)
    * [getTypes()](#gettypes)
    * [getLocation()](#getlocation)
  * [Proxy](#proxy)
    * [Functions](#functions)
    * [Processing](#processing)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

## Quickstart example

Once [installed](#install), you can use the following code to query an example
web service via SOAP:

```php
$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);
$wsdl = 'http://example.com/demo.wsdl';

$factory->createClient($wsdl)->then(function (Client $client) {
    $api = new Proxy($client);

    $api->getBank(array('blz' => '12070000'))->then(function ($result) {
        var_dump('Result', $result);
    });
});

$loop->run();
```

See also the [examples](examples).

## Usage

### Factory

The `Factory` class is responsible for fetching the WSDL file once and constructing
the [`Client`](#client) instance.
It also registers everything with the main [`EventLoop`](https://github.com/reactphp/event-loop#usage).

```php
$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);
```

If you need custom DNS or proxy settings, you can explicitly pass a
custom [`Browser`](https://github.com/clue/php-buzz-react#browser) instance:

```php
$browser = new Clue\React\Buzz\Browser($loop);
$factory = new Factory($loop, $browser);
```

#### createClient()

The `createClient($wsdl)` method can be used to download the WSDL at the
given URL into memory and create a new [`Client`](#client).

```php
$factory->createClient($url)->then(
    function (Client $client) {
        // client ready
    },
    function (Exception $e) {
        // an error occured while trying to download the WSDL
    }
);
```

#### createClientFromWsdl()

The `createClientFromWsdl($wsdlContents)` method works similar to `createClient()`,
but leaves you the responsibility to load the WSDL file.
This allows you to use local WSDL files, for instance.

### Client

The `Client` class is responsible for communication with the remote SOAP
WebService server.

If you want to call RPC functions, see below for the [`Proxy`](#proxy) class.

Note: It's recommended (and easier) to wrap the `Client` in a [`Proxy`](#proxy) instance.
All public methods of the `Client` are considered *advanced usage*.

#### soapCall()

The `soapCall($method, $arguments)` method can be used to queue the given
function to be sent via SOAP and wait for a response from the remote web service.

Note: This is considered *advanced usage*, you may want to look into using the [`Proxy`](#proxy) instead.

#### getFunctions()

The `getFunctions()` method returns an array of functions defined in the WSDL.
It returns the equivalent of PHP's [`SoapClient::__getFunctions()`](http://php.net/manual/en/soapclient.getfunctions.php).

#### getTypes()

The `getTypes()` method returns an array of types defined in the WSDL.
It returns the equivalent of PHP's [`SoapClient::__getTypes()`](http://php.net/manual/en/soapclient.gettypes.php).

#### getLocation()

The `getLocation($function)` method can be used to return the location (URI)
of the given webservice `$function`.

Note that this is not to be confused with the WSDL file location.
A WSDL file can contain any number of function definitions.
It's very common that all of these functions use the same location definition.
However, technically each function can potentially use a different location.

The `$function` parameter should be a string with the the SOAP function name.
See also [`getFunctions()`](#getfunctions) for a list of all available functions.

For easier access, this function also accepts a numeric function index.
It then uses [`getFunctions()`](#getfunctions) internally to get the function
name for the given index.
This is particularly useful for the very common case where all functions use the
same location and accessing the first location is sufficient.

```php
assert('http://example.com/soap/service' == $client->getLocation('echo'));
assert('http://example.com/soap/service' == $client->getLocation(0));
```

Passing a `$function` not defined in the WSDL file will throw a `SoapFault`. 

#### withHeaders(array $headers)

This method allows you to specify HTTP headers used when making SOAP calls. It returns a new Client instance
that's configured to use the specified headers.


#### withRequestTarget($requestTarget)

This method allows you to change the destination of your SOAP calls. It returns a new Client instance
with the specified request-target.

### Proxy

The `Proxy` class wraps an existing [`Client`](#client) instance in order to ease calling
SOAP functions.

```php
$proxy = new Proxy($client);
```

#### Functions

Each and every method call to the `Proxy` class will be sent via SOAP.

```php
$proxy->myMethod($myArg1, $myArg2)->then(function ($response) {
    // result received
});
```

Please refer to your WSDL or its accompanying documentation for details
on which functions and arguments are supported.

#### Processing

Issuing SOAP functions is async (non-blocking), so you can actually send multiple RPC requests in parallel.
The web service will respond to each request with a return value. The order is not guaranteed.
Sending requests uses a [Promise](https://github.com/reactphp/promise)-based interface that makes it easy to react to when a request is *fulfilled*
(i.e. either successfully resolved or rejected with an error):

```php
$proxy->demo()->then(
    function ($response) {
        // response received for demo function
    },
    function (Exception $e) {
        // an error occured while executing the request
    }
});
```

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require clue/soap-react:^0.2
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

This project aims to run on any platform and thus only requires `ext-soap` and
supports running on legacy PHP 5.3 through current PHP 7+ and HHVM.
It's *highly recommended to use PHP 7+* for this project.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
```

## License

MIT
