<?php

declare(strict_types=1);

namespace Pam\Api;

use Pam\Contracts\Http\RequestHandlerInterface;
use Pam\Http\Request;
use Pam\Http\Response;

/** @internal Precompiled callable middleware node used by the immutable request pipeline. */
final readonly class CompiledCallableMiddlewareHandler implements RequestHandlerInterface
{
    private \Closure $middleware;

    public function __construct(
        callable $middleware,
        private RequestHandlerInterface $next,
    ) {
        $this->middleware = \Closure::fromCallable($middleware);
    }

    public function handle(Request $request, Response $response): Response
    {
        $result = ($this->middleware)($request, $response, $this->next);
        if (!$result instanceof Response) {
            throw new \UnexpectedValueException('Pam middleware must return Pam\\Http\\Response.');
        }
        return $result;
    }
}
