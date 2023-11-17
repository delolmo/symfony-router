<?php

declare(strict_types=1);

namespace DelOlmo\Middleware;

use Http\Discovery\Psr17FactoryDiscovery;
use LogicException;
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

use function class_exists;
use function implode;
use function sprintf;

class SymfonyRouterMiddleware implements Middleware
{
    public function __construct(
        private readonly Router $router,
        private readonly ResponseFactoryInterface|null $responseFactory = null,
    ) {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $responseFactory = $this->responseFactory ??
                $this->getDefaultResponseFactory();

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

    private function getDefaultResponseFactory(): ResponseFactoryInterface
    {
        if (class_exists(Psr17FactoryDiscovery::class)) {
            return Psr17FactoryDiscovery::findResponseFactory();
        }

        $message = 'You cannot use the "%s" as no PSR-17 factories have been '
            . 'provided. Try running "composer require '
            . 'php-http/discovery psr/http-factory-implementation:*".';

        throw new LogicException(sprintf($message, self::class));
    }
}
