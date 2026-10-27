<?php

declare(strict_types=1);

namespace DelOlmo\Middleware\Tests;

use DelOlmo\Middleware\SymfonyRouterMiddleware;
use DelOlmo\Middleware\Tests\Mock\SpyRequestHandler;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(SymfonyRouterMiddleware::class)]
final class SymfonyRouterMiddlewareTest extends TestCase
{
    #[TestDox('A matched route hands the request on with the route parameters as attributes')]
    public function testMatchedRouteStoresParametersAsAttributes(): void
    {
        $routes = new RouteCollection();
        $routes->add('blog_show', new Route('/blog/{slug}', ['handler' => 'show']));

        $handler = new SpyRequestHandler();

        $response = $this->middleware($routes)->process(
            new ServerRequestFactory()->createServerRequest('GET', '/blog/hello-world'),
            $handler,
        );

        $handledRequest = $handler->handledRequest();

        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertNotNull($handledRequest);
        Assert::assertSame(
            ['handler' => 'show', 'slug' => 'hello-world', '_route' => 'blog_show'],
            $handledRequest->getAttributes(),
        );
    }

    #[TestDox('A numeric route parameter name becomes a string attribute name')]
    public function testNumericParameterNameBecomesStringAttribute(): void
    {
        $routes = new RouteCollection();
        $routes->add('numeric', new Route('/numeric', ['zero']));

        $handler = new SpyRequestHandler();

        $this->middleware($routes)->process(
            new ServerRequestFactory()->createServerRequest('GET', '/numeric'),
            $handler,
        );

        Assert::assertSame('zero', $handler->handledRequest()?->getAttribute('0'));
    }

    #[TestDox('The router context takes the request host and scheme, for URLs generated while handling')]
    public function testRouterContextIsUpdatedFromRequest(): void
    {
        $routes = new RouteCollection();
        $routes->add('admin', new Route('/', host: 'admin.example.com'));

        $urlMatcher = new UrlMatcher($routes, new RequestContext());

        $symfonyRouterMiddleware = new SymfonyRouterMiddleware(
            router: $urlMatcher,
            responseFactory: new ResponseFactory(),
        );

        $symfonyRouterMiddleware->process(
            new ServerRequestFactory()->createServerRequest('GET', 'https://admin.example.com/'),
            new SpyRequestHandler(),
        );

        Assert::assertSame('admin.example.com', $urlMatcher->getContext()->getHost());
        Assert::assertSame('https', $urlMatcher->getContext()->getScheme());
    }

    #[TestDox('No matching route answers 404 with the standard reason phrase')]
    public function testUnknownPathAnswersNotFound(): void
    {
        $routes = new RouteCollection();
        $routes->add('users', new Route('/users'));

        $handler = new SpyRequestHandler();

        $response = $this->middleware($routes)->process(
            new ServerRequestFactory()->createServerRequest('GET', '/posts'),
            $handler,
        );

        Assert::assertSame(404, $response->getStatusCode());
        Assert::assertSame('Not Found', $response->getReasonPhrase());
        Assert::assertNull($handler->handledRequest());
    }

    #[TestDox('A method the route does not allow answers 405 with the allowed methods')]
    public function testDisallowedMethodAnswersMethodNotAllowed(): void
    {
        $routes = new RouteCollection();
        $routes->add('users', new Route('/users', methods: ['GET', 'PUT']));

        $handler = new SpyRequestHandler();

        $response = $this->middleware($routes)->process(
            new ServerRequestFactory()->createServerRequest('POST', '/users'),
            $handler,
        );

        Assert::assertSame(405, $response->getStatusCode());
        Assert::assertSame('Method Not Allowed', $response->getReasonPhrase());
        Assert::assertSame('GET, PUT', $response->getHeaderLine('Allow'));
        Assert::assertNull($handler->handledRequest());
    }

    #[TestDox('A router without routes answers 500 with the standard reason phrase')]
    public function testNoRoutesAnswersInternalServerError(): void
    {
        $handler = new SpyRequestHandler();

        $response = $this->middleware(new RouteCollection())->process(
            new ServerRequestFactory()->createServerRequest('GET', '/'),
            $handler,
        );

        Assert::assertSame(500, $response->getStatusCode());
        Assert::assertSame('Internal Server Error', $response->getReasonPhrase());
        Assert::assertNull($handler->handledRequest());
    }

    private function middleware(RouteCollection $routes): SymfonyRouterMiddleware
    {
        return new SymfonyRouterMiddleware(
            router: new UrlMatcher($routes, new RequestContext()),
            responseFactory: new ResponseFactory(),
        );
    }
}
