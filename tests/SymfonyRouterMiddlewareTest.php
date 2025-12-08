<?php

declare(strict_types=1);

namespace DelOlmo\Middleware;

use Equip\Dispatch\MiddlewareCollection;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\MockObject\MockObject;
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

final class SymfonyRouterMiddlewareTest extends TestCase
{
    private RouteCollection $routeCollection;

    private Router $router;

    private MockObject $loader;

    protected function setUp(): void
    {
        $this->loader = $this->getMockBuilder(LoaderInterface::class)->getMock();
        $this->router = new Router($this->loader, 'routing.yml');

        $this->routeCollection = new RouteCollection();
        $this->routeCollection->add('test', new Route('/users', [], [], [], '', [], ['GET']));
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

        $serverRequest = new ServerRequestFactory()
            ->createServerRequest('GET', '/');

        $symfonyRequest = new HttpFoundationFactory()
            ->createRequest($serverRequest);

        $context
            ->expects(self::once())
            ->method('fromRequest')
            ->with($symfonyRequest);

        $router
            ->expects(self::once())
            ->method('matchRequest')
            ->with($symfonyRequest)
            ->will(self::throwException(new ResourceNotFoundException()));

        $symfonyRouterMiddleware = new SymfonyRouterMiddleware($router, $factory);

        new MiddlewareCollection([$symfonyRouterMiddleware])
            ->dispatch($serverRequest, static fn (ServerRequestInterface $serverRequest): Response => new Response());
    }

    public function testRequestContextBeingUpdated(): void
    {
        $context = $this->createMock(RequestContext::class);

        $router = $this->createMock(Router::class);

        $router
            ->expects(self::once())
            ->method('getContext')
            ->willReturn($context);

        $serverRequest = new ServerRequestFactory()
            ->createServerRequest('GET', '/posts');

        $symfonyRequest = new HttpFoundationFactory()
            ->createRequest($serverRequest);

        $context
            ->expects(self::once())
            ->method('fromRequest')
            ->with($symfonyRequest);

        $router
            ->expects(self::once())
            ->method('matchRequest')
            ->with($symfonyRequest)
            ->willReturn([]);

        $responseFactory = new ResponseFactory();

        $symfonyRouterMiddleware = new SymfonyRouterMiddleware($router, $responseFactory);

        new MiddlewareCollection([$symfonyRouterMiddleware])
            ->dispatch($serverRequest, static fn (ServerRequestInterface $serverRequest): Response => new Response());
    }

    public function testResourceNotFoundException(): void
    {
        $serverRequest = new ServerRequestFactory()
            ->createServerRequest('GET', '/posts');

        $httpFoundationFactory = new HttpFoundationFactory();

        $symfonyRequest = $httpFoundationFactory
            ->createRequest($serverRequest);

        $requestContext = new RequestContext()
            ->fromRequest($symfonyRequest);

        $urlMatcher = new UrlMatcher($this->routeCollection, $requestContext);

        $reflectionProperty = new ReflectionProperty($this->router, 'matcher');

        $reflectionProperty->setValue($this->router, $urlMatcher);

        $responseFactory = new ResponseFactory();

        $symfonyRouterMiddleware = new SymfonyRouterMiddleware($this->router, $responseFactory);

        $response = new MiddlewareCollection([$symfonyRouterMiddleware])
            ->dispatch($serverRequest, static fn (ServerRequestInterface $serverRequest): Response => new Response());

        self::assertSame(404, $response->getStatusCode());
    }

    public function testMethodNotAllowedException(): void
    {
        $serverRequest = new ServerRequestFactory()
            ->createServerRequest('POST', '/users');

        $httpFoundationFactory = new HttpFoundationFactory();

        $symfonyRequest = $httpFoundationFactory->createRequest($serverRequest);

        $requestContext = new RequestContext()
            ->fromRequest($symfonyRequest);

        $urlMatcher = new UrlMatcher($this->routeCollection, $requestContext);

        $reflectionProperty = new ReflectionProperty($this->router, 'matcher');

        $reflectionProperty->setValue($this->router, $urlMatcher);

        $responseFactory = new ResponseFactory();

        $symfonyRouterMiddleware = new SymfonyRouterMiddleware($this->router, $responseFactory);

        $response = new MiddlewareCollection([$symfonyRouterMiddleware])
            ->dispatch($serverRequest, static fn (ServerRequestInterface $serverRequest): Response => new Response());

        self::assertSame(405, $response->getStatusCode());
    }

    public function testNoConfigurationException(): void
    {
        $serverRequest = new ServerRequestFactory()
            ->createServerRequest('POST', '/posts');

        $matcher = $this->createMock(RequestMatcherInterface::class);

        $matcher->method('matchRequest')
            ->will(self::throwException(new NoConfigurationException()));

        $reflectionProperty = new ReflectionProperty($this->router, 'matcher');

        $reflectionProperty->setValue($this->router, $matcher);

        $responseFactory = new ResponseFactory();

        $symfonyRouterMiddleware = new SymfonyRouterMiddleware($this->router, $responseFactory);

        $response = new MiddlewareCollection([$symfonyRouterMiddleware])
            ->dispatch($serverRequest, static fn (ServerRequestInterface $serverRequest): Response => new Response());

        self::assertSame(500, $response->getStatusCode());
    }

    public function testRouteMatched(): void
    {
        $serverRequest = new ServerRequestFactory()
            ->createServerRequest('GET', '/users');

        $httpFoundationFactory = new HttpFoundationFactory();

        $symfonyRequest = $httpFoundationFactory->createRequest($serverRequest);

        $requestContext = new RequestContext()
            ->fromRequest($symfonyRequest);

        $urlMatcher = new UrlMatcher($this->routeCollection, $requestContext);

        $reflectionProperty = new ReflectionProperty($this->router, 'matcher');

        $reflectionProperty->setValue($this->router, $urlMatcher);

        $responseFactory = new ResponseFactory();

        $symfonyRouterMiddleware = new SymfonyRouterMiddleware($this->router, $responseFactory);

        new MiddlewareCollection([$symfonyRouterMiddleware])
            ->dispatch($serverRequest, static function (ServerRequestInterface $serverRequest): Response {
                self::assertSame('test', $serverRequest->getAttribute('_route'));

                return new Response();
            });
    }
}
