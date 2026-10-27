<?php

declare(strict_types=1);

namespace DelOlmo\Middleware;

use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContextAwareInterface;

use function array_keys;
use function implode;

/**
 * Matches the request against Symfony routes and stores the route's
 * parameters as request attributes before handing it on. The router's
 * context takes the request's host, scheme and base URL first, so URLs a
 * Router generates while the request is handled match it. Answers 404 when
 * no route matches, 405 with an Allow header when the method is not
 * allowed, and 500 when there are no routes at all. Error responses carry
 * the standard reason phrase, never the routing exception's message.
 */
final readonly class SymfonyRouterMiddleware implements Middleware
{
    private HttpFoundationFactory $httpFoundationFactory;

    /** @param RequestMatcherInterface&RequestContextAwareInterface $router A Router, or a UrlMatcher */
    public function __construct(
        private RequestMatcherInterface&RequestContextAwareInterface $router,
        private ResponseFactoryInterface $responseFactory,
    ) {
        $this->httpFoundationFactory = new HttpFoundationFactory();
    }

    #[Override]
    public function process(Request $request, Handler $handler): Response
    {
        $symfonyRequest = $this->httpFoundationFactory->createRequest($request);

        $this->router->getContext()->fromRequest($symfonyRequest);

        try {
            $parameters = $this->router->matchRequest($symfonyRequest);
        } catch (MethodNotAllowedException $e) {
            return $this->responseFactory
                ->createResponse(405)
                ->withHeader('Allow', implode(', ', $e->getAllowedMethods()));
        } catch (NoConfigurationException) {
            return $this->responseFactory->createResponse(500);
        } catch (ResourceNotFoundException) {
            return $this->responseFactory->createResponse(404);
        }

        foreach (array_keys($parameters) as $name) {
            $request = $request->withAttribute((string) $name, $parameters[$name]);
        }

        return $handler->handle($request);
    }
}
