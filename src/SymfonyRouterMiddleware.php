<?php

declare(strict_types=1);

namespace DelOlmo\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Router;
use function implode;

class SymfonyRouterMiddleware implements Middleware
{
    private ResponseFactoryInterface $responseFactory;

    private Router $router;

    public function __construct(
        Router $router,
        ResponseFactoryInterface $responseFactory
    ) {
        $this->router = $router;

        $this->responseFactory = $responseFactory;
    }

    public function process(Request $request, Handler $handler) : Response
    {
        try {
            $symfonyRequest = (new HttpFoundationFactory())
                ->createRequest($request);

            $this->router->getContext()->fromRequest($symfonyRequest);

            /** @psalm-var array<string,mixed> $route */
            $route = $this->router
                ->matchRequest($symfonyRequest);
        } catch (MethodNotAllowedException $e) {
            $allows = implode(', ', $e->getAllowedMethods());

            return $this->responseFactory
                ->createResponse(405, $e->getMessage())
                ->withHeader('Allow', $allows);
        } catch (NoConfigurationException $e) {
            return $this->responseFactory
                ->createResponse(500, $e->getMessage());
        } catch (ResourceNotFoundException $e) {
            return $this->responseFactory
                ->createResponse(404, $e->getMessage());
        }

        /** @psalm-var mixed $value */
        foreach ($route as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $handler->handle($request);
    }
}
