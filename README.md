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

Consider Symfony's PhpFileLoader to load route definitions from the following file:

``` php
# routes.php

use App\Controller\BlogController;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

$routes = new RouteCollection();
$routes->add('blog_list', new Route('/blog', array(
    'request-handler' => [BlogController::class, 'list']
)));
$routes->add('blog_show', new Route('/blog/{slug}', array(
    'request-handler' => [BlogController::class, 'show']
)));

return $routes;

```

This example uses a basic anonymous function to print the route's attributes:

```php

use Middlewares\Utils\Dispatcher;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\ServerRequest;

$fileLocator = new FileLocator(array(__DIR__));

$router = new Router(
    new PhpFileLoader($fileLocator),
    'routes.php',
    array('cache_dir' => __DIR__ . '/cache'),
    new RequestContext('/')
);

$dispatcher = new Dispatcher([
    new DelOlmo\Middleware\SymfonyRouterMiddleware($router),
    function($request, $next) {
        return new HtmlResponse(json_encode($request->getAttributes()));
    }
]);

// Try matching a /blog request
$response = $dispatcher->dispatch(new ServerRequest([], [], '/blog'));

// Will return {"_route": "blog_list", "request-handler" => ["App\Controller\BlogController", "list"]}
$c->get('emitter')->emit($response);

// Try matching a /blog/hello-world request
$response = $dispatcher->dispatch(new ServerRequest([], [], '/blog/hello-world'));

// Will return {"_route": "blog_show", "request-handler" => ["App\Controller\BlogController", "show"], "slug" => "hello-world"}
$c->get('emitter')->emit($response);

```

## Options

#### `__construct(Symfony\Component\Routing\RouterInterface $router)`

The router instance to use.

#### `responseFactory(Psr\Http\Message\ResponseFactoryInterface $responseFactory)`

A PSR-17 factory to create the error responses (`404` or `405`).
