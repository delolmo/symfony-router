<?php

declare(strict_types=1);

namespace DelOlmo\Middleware\Tests\Mock;

use Laminas\Diactoros\Response;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Records the request it handles and answers with an empty 200. */
final class SpyRequestHandler implements RequestHandlerInterface
{
    private ServerRequestInterface|null $handledRequest = null;

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->handledRequest = $request;

        return new Response();
    }

    public function handledRequest(): ServerRequestInterface|null
    {
        return $this->handledRequest;
    }
}
