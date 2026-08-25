<?php

declare(strict_types=1);

namespace Pam\Api;

use Pam\Contracts\Http\MiddlewareInterface;
use Pam\Contracts\Http\RequestHandlerInterface;
use Pam\Http\Request;
use Pam\Http\Response;

/** @internal Precompiled middleware node used by the immutable request pipeline. */
final readonly class CompiledMiddlewareHandler implements RequestHandlerInterface
{
    public function __construct(
        private MiddlewareInterface $middleware,
        private RequestHandlerInterface $next,
    ) {
    }

    public function handle(Request $request, Response $response): Response
    {
        return $this->middleware->process($request, $response, $this->next);
    }
}
