<?php

declare(strict_types = 1);

namespace DelOlmo\Middleware;

use DelOlmo\Middleware\SymfonyRouterMiddleware;
use Middlewares\Utils\Dispatcher;
use Middlewares\Utils\Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Router;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class SymfonyRouterMiddlewareTest extends TestCase
{

    /**
     *
     * @var \Symfony\Component\Routing\RouteCollection
     */
    private $routes = null;

    /**
     *
     * @var \Symfony\Component\Routing\Router
     */
    private $router = null;

    /**
     *
     * @var \Symfony\Component\Config\Loader\LoaderInterface
     */
    private $loader = null;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->loader = $this->getMockBuilder(LoaderInterface::class)->getMock();
        $this->router = new Router($this->loader, 'routing.yml');

        $this->routes = new RouteCollection();
        $this->routes->add('test', new Route('/users', [], [], [], '', [], ['GET']));
    }

    /**
     *
     */
    public function testResourceNotFoundException()
    {
        $request = Factory::createServerRequest('GET', '/posts');

        $factory = new HttpFoundationFactory();

        $symfonyRequest = $factory->createRequest($request);

        $context = (new RequestContext())
            ->fromRequest($symfonyRequest);

        $matcher = new UrlMatcher($this->routes, $context);

        $p = new \ReflectionProperty($this->router, 'matcher');

        $p->setAccessible(true);

        $p->setValue($this->router, $matcher);

        $response = Dispatcher::run([
                new SymfonyRouterMiddleware($this->router)
                ], $request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     *
     */
    public function testMethodNotAllowedException()
    {
        $request = Factory::createServerRequest('POST', '/users');

        $factory = new HttpFoundationFactory();

        $symfonyRequest = $factory->createRequest($request);

        $context = (new RequestContext())
            ->fromRequest($symfonyRequest);

        $matcher = new UrlMatcher($this->routes, $context);

        $p = new \ReflectionProperty($this->router, 'matcher');

        $p->setAccessible(true);

        $p->setValue($this->router, $matcher);

        $response = Dispatcher::run([
                new SymfonyRouterMiddleware($this->router)
                ], $request);

        $this->assertEquals(405, $response->getStatusCode());
    }

    /**
     *
     */
    public function testNoConfigurationException()
    {
        $request = Factory::createServerRequest('POST', '/users');

        $matcher = $this->createMock(RequestMatcherInterface::class);

        $matcher->method("matchRequest")
            ->will($this->throwException(new NoConfigurationException()));

        $p = new \ReflectionProperty($this->router, "matcher");

        $p->setAccessible(true);

        $p->setValue($this->router, $matcher);

        $response = Dispatcher::run([
                new SymfonyRouterMiddleware($this->router)
                ], $request);

        $this->assertEquals(500, $response->getStatusCode());
    }

    /**
     *
     */
    public function testRouteMatched()
    {
        $request = Factory::createServerRequest('GET', '/users');

        $factory        = new HttpFoundationFactory();
        $symfonyRequest = $factory->createRequest($request);
        $context        = (new RequestContext())->fromRequest($symfonyRequest);
        $matcher        = new UrlMatcher($this->routes, $context);
        $p              = new \ReflectionProperty($this->router, 'matcher');
        $p->setAccessible(true);
        $p->setValue($this->router, $matcher);

        $response = Dispatcher::run([
                new SymfonyRouterMiddleware($this->router),
                function ($request) {
                    echo $request->getAttribute('_route');
                }
                ], $request);

        $this->assertEquals('test', (string) $response->getBody());
    }
}
