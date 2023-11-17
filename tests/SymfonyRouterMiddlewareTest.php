<?php

declare(strict_types=1);

namespace DelOlmo\Middleware;

use Equip\Dispatch\MiddlewareCollection;
use Http\Discovery\Psr17Factory;
use Laminas\Diactoros\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

class SymfonyRouterMiddlewareTest extends TestCase
{
    private RouteCollection $routes;

    private Router $router;

    private LoaderInterface $loader;

    protected function setUp(): void
    {
        $this->loader = $this->getMockBuilder(LoaderInterface::class)->getMock();
        $this->router = new Router($this->loader, 'routing.yml');

        $this->routes = new RouteCollection();
        $this->routes->add('test', new Route('/users', [], [], [], '', [], ['GET']));
    }

    public function testNonDefaultResponseFactory(): void
    {
        $factory = $this->createMock(ResponseFactoryInterface::class);

        $factory
          ->expects(self::once())
          ->method('createResponse')
          ->with(404);

        $context = $this->createMock(RequestContext::class);

        $router = $this->createMock(Router::class);

        $router
            ->expects(self::once())
            ->method('getContext')
            ->willReturn($context);

        $request = (new Psr17Factory())->createServerRequest('GET', '/');

        $symfonyRequest = (new HttpFoundationFactory())
            ->createRequest($request);

        $context
            ->expects(self::once())
            ->method('fromRequest')
            ->with($symfonyRequest);

        $router
            ->expects(self::once())
            ->method('matchRequest')
            ->with($symfonyRequest)
            ->will(self::throwException(new ResourceNotFoundException()));

        $middleware = new SymfonyRouterMiddleware($router, $factory);

        (new MiddlewareCollection([$middleware]))
            ->dispatch($request, static fn (ServerRequestInterface $request) => new Response());
    }

    public function testRequestContextBeingUpdated(): void
    {
        $context = $this->createMock(RequestContext::class);

        $router = $this->createMock(Router::class);

        $router
            ->expects(self::once())
            ->method('getContext')
            ->willReturn($context);

        $request = (new Psr17Factory())->createServerRequest('GET', '/posts');

        $symfonyRequest = (new HttpFoundationFactory())
            ->createRequest($request);

        $context
            ->expects(self::once())
            ->method('fromRequest')
            ->with($symfonyRequest);

        $router
            ->expects(self::once())
            ->method('matchRequest')
            ->with($symfonyRequest)
            ->willReturn([]);

        $middleware = new SymfonyRouterMiddleware($router);

        (new MiddlewareCollection([$middleware]))
            ->dispatch($request, static fn (ServerRequestInterface $request) => new Response());
    }

    public function testResourceNotFoundException(): void
    {
        $request = (new Psr17Factory())->createServerRequest('GET', '/posts');

        $factory = new HttpFoundationFactory();

        $symfonyRequest = $factory->createRequest($request);

        $context = (new RequestContext())
            ->fromRequest($symfonyRequest);

        $matcher = new UrlMatcher($this->routes, $context);

        $p = new ReflectionProperty($this->router, 'matcher');

        $p->setAccessible(true);

        $p->setValue($this->router, $matcher);

        $middleware = new SymfonyRouterMiddleware($this->router);

        $response = (new MiddlewareCollection([$middleware]))
            ->dispatch($request, static fn (ServerRequestInterface $request) => new Response());

        self::assertSame(404, $response->getStatusCode());
    }

    public function testMethodNotAllowedException(): void
    {
        $request = (new Psr17Factory())->createServerRequest('POST', '/users');

        $factory = new HttpFoundationFactory();

        $symfonyRequest = $factory->createRequest($request);

        $context = (new RequestContext())
            ->fromRequest($symfonyRequest);

        $matcher = new UrlMatcher($this->routes, $context);

        $p = new ReflectionProperty($this->router, 'matcher');

        $p->setAccessible(true);

        $p->setValue($this->router, $matcher);

        $middleware = new SymfonyRouterMiddleware($this->router);

        $response = (new MiddlewareCollection([$middleware]))
            ->dispatch($request, static fn (ServerRequestInterface $request) => new Response());

        self::assertSame(405, $response->getStatusCode());
    }

    public function testNoConfigurationException(): void
    {
        $request = (new Psr17Factory())->createServerRequest('POST', '/posts');

        $matcher = $this->createMock(RequestMatcherInterface::class);

        $matcher->method('matchRequest')
            ->will(self::throwException(new NoConfigurationException()));

        $p = new ReflectionProperty($this->router, 'matcher');

        $p->setAccessible(true);

        $p->setValue($this->router, $matcher);

        $middleware = new SymfonyRouterMiddleware($this->router);

        $response = (new MiddlewareCollection([$middleware]))
            ->dispatch($request, static fn (ServerRequestInterface $request) => new Response());

        self::assertSame(500, $response->getStatusCode());
    }

    public function testRouteMatched(): void
    {
        $request = (new Psr17Factory())->createServerRequest('GET', '/users');

        $factory = new HttpFoundationFactory();

        $symfonyRequest = $factory->createRequest($request);

        $context = (new RequestContext())
            ->fromRequest($symfonyRequest);

        $matcher = new UrlMatcher($this->routes, $context);

        $p = new ReflectionProperty($this->router, 'matcher');

        $p->setAccessible(true);

        $p->setValue($this->router, $matcher);

        $middleware = new SymfonyRouterMiddleware($this->router);

        (new MiddlewareCollection([$middleware]))
            ->dispatch($request, static function (ServerRequestInterface $request) {
                self::assertSame('test', $request->getAttribute('_route'));

                return new Response();
            });
    }
}
