<?php

declare(strict_types=1);

namespace Pam\Api;

use Pam\Contracts\Http\MiddlewareInterface;
use Pam\Contracts\Http\RequestHandlerInterface;
use Pam\Http\Request;
use Pam\Http\Response;

final class Pipeline implements RequestHandlerInterface
{
    private RequestHandlerInterface $compiled;

    /** @param list<MiddlewareInterface|callable> $middleware */
    public function __construct(array $middleware, RequestHandlerInterface $destination)
    {
        $next = $destination;
        foreach (array_reverse($middleware) as $layer) {
            $next = $layer instanceof MiddlewareInterface
                ? new CompiledMiddlewareHandler($layer, $next)
                : new CompiledCallableMiddlewareHandler($layer, $next);
        }
        $this->compiled = $next;
    }

    public function handle(Request $request, Response $response): Response
    {
        return $this->compiled->handle($request, $response);
    }
}
