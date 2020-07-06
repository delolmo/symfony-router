<?php

declare(strict_types=1);

namespace DelOlmo\Middleware;

use Middlewares\Utils\Dispatcher;
use Middlewares\Utils\Factory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Routing\Exception\NoConfigurationException;
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

    protected function setUp() : void
    {
        $this->loader = $this->getMockBuilder(LoaderInterface::class)->getMock();
        $this->router = new Router($this->loader, 'routing.yml');

        $this->routes = new RouteCollection();
        $this->routes->add('test', new Route('/users', [], [], [], '', [], ['GET']));
    }

    public function testRequestContextBeingUpdated() : void
    {
        $context = $this->createMock(RequestContext::class);

        $router = $this->createMock(Router::class);

        $router
            ->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        $request = Factory::createServerRequest('GET', '/posts');

        $symfonyRequest = (new HttpFoundationFactory())
            ->createRequest($request);

        $context
            ->expects($this->once())
            ->method('fromRequest')
            ->with($symfonyRequest);

        $router
            ->expects($this->once())
            ->method('matchRequest')
            ->with($symfonyRequest)
            ->willReturn([]);

        $factory = Factory::getResponseFactory();

        $middleware = new SymfonyRouterMiddleware($router, $factory);

        Dispatcher::run([$middleware], $request);
    }

    public function testResourceNotFoundException() : void
    {
        $request = Factory::createServerRequest('GET', '/posts');

        $factory = new HttpFoundationFactory();

        $symfonyRequest = $factory->createRequest($request);

        $context = (new RequestContext())
            ->fromRequest($symfonyRequest);

        $matcher = new UrlMatcher($this->routes, $context);

        $p = new ReflectionProperty($this->router, 'matcher');

        $p->setAccessible(true);

        $p->setValue($this->router, $matcher);

        $factory = Factory::getResponseFactory();

        $middleware = new SymfonyRouterMiddleware($this->router, $factory);

        $response = Dispatcher::run([$middleware], $request);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testMethodNotAllowedException() : void
    {
        $request = Factory::createServerRequest('POST', '/users');

        $factory = new HttpFoundationFactory();

        $symfonyRequest = $factory->createRequest($request);

        $context = (new RequestContext())
            ->fromRequest($symfonyRequest);

        $matcher = new UrlMatcher($this->routes, $context);

        $p = new ReflectionProperty($this->router, 'matcher');

        $p->setAccessible(true);

        $p->setValue($this->router, $matcher);

        $factory = Factory::getResponseFactory();

        $middleware = new SymfonyRouterMiddleware($this->router, $factory);

        $response = Dispatcher::run([$middleware], $request);

        self::assertSame(405, $response->getStatusCode());
    }

    public function testNoConfigurationException() : void
    {
        $request = Factory::createServerRequest('POST', '/users');

        $matcher = $this->createMock(RequestMatcherInterface::class);

        $matcher->method('matchRequest')
            ->will(self::throwException(new NoConfigurationException()));

        $p = new ReflectionProperty($this->router, 'matcher');

        $p->setAccessible(true);

        $p->setValue($this->router, $matcher);

        $factory = Factory::getResponseFactory();

        $middleware = new SymfonyRouterMiddleware($this->router, $factory);

        $response = Dispatcher::run([$middleware], $request);

        self::assertSame(500, $response->getStatusCode());
    }

    public function testRouteMatched() : void
    {
        $request = Factory::createServerRequest('GET', '/users');

        $factory = new HttpFoundationFactory();

        $symfonyRequest = $factory->createRequest($request);

        $context = (new RequestContext())
            ->fromRequest($symfonyRequest);

        $matcher = new UrlMatcher($this->routes, $context);

        $p = new ReflectionProperty($this->router, 'matcher');

        $p->setAccessible(true);

        $p->setValue($this->router, $matcher);

        $factory = Factory::getResponseFactory();

        $middleware = new SymfonyRouterMiddleware($this->router, $factory);

        $dummyFn = static function ($request) : void {
            echo $request->getAttribute('_route');
        };

        $response = Dispatcher::run([$middleware, $dummyFn], $request);

        self::assertSame('test', (string) $response->getBody());
    }
}
