# delolmo/symfony-router

 [![Packagist Version](https://img.shields.io/packagist/v/delolmo/symfony-router.svg?style=flat-square)](https://packagist.org/packages/delolmo/symfony-router)
 [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
 [![Build Status](https://travis-ci.org/delolmo/symfony-router.svg)](https://travis-ci.org/delolmo/symfony-router)

PSR-15 middleware to use the symfony/routing component and store the route attributes in the request.

## Requirements

* PHP >= 7.1
* A [PSR-7 http library](https://github.com/middlewares/awesome-psr15-middlewares#psr-7-implementations)
* A [PSR-15 middleware dispatcher](https://github.com/middlewares/awesome-psr15-middlewares#dispatcher)

## Installation

This package is installable and autoloadable via Composer as [delolmo/symfony-router](https://packagist.org/packages/delolmo/symfony-router).

```sh
composer require delolmo/symfony-router
```

You may also want to install [middlewares/request-handler](https://packagist.org/packages/middlewares/request-handler).

## Example

This example uses [middleware/request-handler](https://github.com/middlewares/request-handler) to execute the route handler:

```php

//Create the router
$router = new Router($loader, $path, $options, $context);

$dispatcher = new Dispatcher([
    new DelOlmo\Middleware\SymfonyRouterMiddleware($router),
    new Middlewares\RequestHandler()
]);

$response = $dispatcher->dispatch(new ServerRequest('/hello/world'));
```

## Options

#### `__construct(Symfony\Component\Routing\RouterInterface $router)`

The router instance to use.

#### `responseFactory(Psr\Http\Message\ResponseFactoryInterface $responseFactory)`

A PSR-17 factory to create the error responses (`404` or `405`).
