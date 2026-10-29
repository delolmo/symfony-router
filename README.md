# delolmo/symfony-router

[![Packagist Version](https://img.shields.io/packagist/v/delolmo/symfony-router.svg?style=flat-square)](https://packagist.org/packages/delolmo/symfony-router)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

PSR-15 middleware to use the symfony/routing component and store the route attributes in the request.

## Requirements

* PHP ~8.4.27 || ^8.5.12
* A [PSR-7 http library](https://github.com/middlewares/awesome-psr15-middlewares#psr-7-implementations)
* A [PSR-17 http factory](https://www.php-fig.org/psr/psr-17/)

## Installation

This package is installable and autoloadable via Composer as [delolmo/symfony-router](https://packagist.org/packages/delolmo/symfony-router).

```sh
composer require delolmo/symfony-router
```

To load route definitions from files through Symfony's `Router`, as in the example below, also require `symfony/config`:

```sh
composer require symfony/config
```

## Example

Consider Symfony's PhpFileLoader to load route definitions from the following file:

```php
# routes.php

use App\Controller\BlogController;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

$routes = new RouteCollection();
$routes->add('blog_list', new Route('/blog', [
    'request-handler' => [BlogController::class, 'list'],
]));
$routes->add('blog_show', new Route('/blog/{slug}', [
    'request-handler' => [BlogController::class, 'show'],
]));

return $routes;
```

For this example, we will be using `middlewares/utils` for a PSR-15 compliant
dispatcher. See [link](https://github.com/middlewares/awesome-psr15-middlewares#dispatcher)
for more PSR-15 implementations.

This example uses a basic anonymous function to print the route's attributes:

```php
use DelOlmo\Middleware\SymfonyRouterMiddleware;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Middlewares\Utils\Dispatcher;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;

$router = new Router(
    new PhpFileLoader(new FileLocator([__DIR__])),
    'routes.php',
    ['cache_dir' => __DIR__ . '/cache'],
    new RequestContext('/'),
);

$dispatcher = new Dispatcher([
    new SymfonyRouterMiddleware(router: $router, responseFactory: new ResponseFactory()),
    fn ($request) => new JsonResponse($request->getAttributes()),
]);

// {"request-handler": ["App\\Controller\\BlogController", "list"], "_route": "blog_list"}
$response = $dispatcher->dispatch(new ServerRequest([], [], '/blog'));

// {"request-handler": ["App\\Controller\\BlogController", "show"], "slug": "hello-world", "_route": "blog_show"}
$response = $dispatcher->dispatch(new ServerRequest([], [], '/blog/hello-world'));
```

## Mapping requests with Valinor

The route parameters are request attributes, so a handler further down the pipeline can pass them to [cuyz/valinor](https://github.com/cuyz/valinor) as the route parameters of an `HttpRequest` and map the whole request to a typed object:

```php
use CuyZ\Valinor\Mapper\Http\FromBody;
use CuyZ\Valinor\Mapper\Http\FromRoute;
use CuyZ\Valinor\Mapper\Http\HttpRequest;
use CuyZ\Valinor\MapperBuilder;

final class PostComment
{
    public function __construct(
        #[FromRoute] public string $slug,
        #[FromBody] public string $content,
    ) {}
}

$mapper = new MapperBuilder()
    ->allowSuperfluousKeys() // the attributes also hold _route and the route defaults
    ->allowScalarValueCasting()
    ->mapper();

$comment = $mapper->map(PostComment::class, HttpRequest::fromPsr($request, $request->getAttributes()));
```

## Options

The constructor takes two arguments:

```php
__construct(
    \Symfony\Component\Routing\Matcher\RequestMatcherInterface&\Symfony\Component\Routing\RequestContextAwareInterface $router,
    \Psr\Http\Message\ResponseFactoryInterface $responseFactory,
)
```

The router to match with (a Symfony `Router`, or a `UrlMatcher` for routes built in code) and a PSR-17 factory to create the error responses:

| Outcome | Response |
|---|---|
| No route matches the path | `404 Not Found` |
| A route matches the path but not the method | `405 Method Not Allowed`, with an `Allow` header listing the allowed methods |
| The router has no routes at all | `500 Internal Server Error` |

Error responses carry the standard reason phrase, never the routing exception's message, which can echo the requested path.

Before matching, the router's context takes the request's host, scheme and base URL, so URLs a `Router` generates while the request is handled match it.
