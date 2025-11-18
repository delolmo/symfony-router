# delolmo/symfony-router

 [![Packagist Version](https://img.shields.io/packagist/v/delolmo/symfony-router.svg?style=flat-square)](https://packagist.org/packages/delolmo/symfony-router)
 [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

PSR-15 middleware to use the symfony/routing component and store the route attributes in the request.

## Requirements

* PHP ^8.2
* A [PSR-7 http library](https://github.com/middlewares/awesome-psr15-middlewares#psr-7-implementations)
* A [PSR-17 http factory](https://www.php-fig.org/psr/psr-17/)

## Installation

This package is installable and autoloadable via Composer as [delolmo/symfony-router](https://packagist.org/packages/delolmo/symfony-router).

```sh
composer require delolmo/symfony-router
```

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

For this example, we will be using `middlewares/utils` for a PSR-15 compliant 
dispatcher. See [link](https://github.com/middlewares/awesome-psr15-middlewares#dispatcher)
for more PSR-15 implementations.

This example uses a basic anonymous function to print the route's attributes:

```php

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Middlewares\Utils\Dispatcher;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;

$fileLocator = new FileLocator(array(__DIR__));

$router = new Router(
    new PhpFileLoader($fileLocator),
    'routes.php',
    array('cache_dir' => __DIR__ . '/cache'),
    new RequestContext('/')
);

$responseFactory = new ResponseFactory();

$dispatcher = new Dispatcher([
    new DelOlmo\Middleware\SymfonyRouterMiddleware($router, $responseFactory),
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

The constructor takes two arguments:

```php
__construct(
    \Symfony\Component\Routing\Router $router,
    \Psr\Http\Message\ResponseFactoryInterface $responseFactory
)
```

The router instance to use and a PSR-17 factory to create the error 
responses (`404` or `405`).
