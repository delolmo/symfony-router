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
use Symfony\Component\Routing\Router;

use function implode;

final readonly class SymfonyRouterMiddleware implements Middleware
{
    public function __construct(
        private Router $router,
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    #[Override]
    public function process(Request $request, Handler $handler): Response
    {
        $responseFactory = $this->responseFactory;

        try {
            $symfonyRequest = (new HttpFoundationFactory())
                ->createRequest($request);

            $this->router->getContext()->fromRequest($symfonyRequest);

            /** @psalm-var array<string,mixed> $route */
            $route = $this->router
                ->matchRequest($symfonyRequest);
        } catch (MethodNotAllowedException $e) {
            $allows = implode(', ', $e->getAllowedMethods());

            return $responseFactory
                ->createResponse(405, $e->getMessage())
                ->withHeader('Allow', $allows);
        } catch (NoConfigurationException $e) {
            return $responseFactory
                ->createResponse(500, $e->getMessage());
        } catch (ResourceNotFoundException $e) {
            return $responseFactory
                ->createResponse(404, $e->getMessage());
        }

        /** @psalm-var mixed $value */
        foreach ($route as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $handler->handle($request);
    }
}
