<?php
declare(strict_types = 1);

namespace DelOlmo\Middleware;

use Middlewares\Utils\Traits\HasResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\RouterInterface as Router;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class SymfonyRouterMiddleware implements Middleware
{
    use HasResponseFactory;

    /**
     * @var string
     */
    private $attribute = '_route';

    /**
     * @var Symfony\Component\Routing\RouterInterface
     */
    private $router;

    /**
     * @param Symfony\Component\Routing\RouterInterface $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Process a request and return a response.
     */
    public function process(Request $request, Handler $handler): Response
    {
        try {
            $symfonyRequest = (new HttpFoundationFactory())
                ->createRequest($request);
            $route = $this->router
                ->matchRequest($symfonyRequest);
        } catch (ResourceNotFoundException $e) {
            return $this->createResponse(404);
        } catch (MethodNotAllowedException $e) {
            $allows = implode(', ', $e->getAllowedMethods());
            return $this->createResponse(405)
                    ->withHeader('Allow', $allows);
        } catch (\Exception $e) {
            return $this->createResponse(500);
        }

        $request = $request->withAttribute($this->attribute, $route);

        return $handler->handle($request);
    }

    /**
     * Set the attribute name to store the route reference.
     *
     * @param string $attribute
     * @return \self
     */
    public function setAttribute(string $attribute): self
    {
        $this->attribute = $attribute;
        return $this;
    }
}
