<?php

declare(strict_types=1);

namespace Pam\Api;

use Pam\Contracts\Http\MiddlewareInterface;

final readonly class PendingRoute
{
    public function __construct(
        private Router $router,
        private Route $route,
    ) {
    }

    public function name(string $name): self
    {
        $this->router->name($this->route, $name);
        return $this;
    }

    public function where(string $parameter, string|RouteConstraint $constraint): self
    {
        $this->router->constrain($this->route, $parameter, $constraint);
        return $this;
    }

    public function middleware(object|callable $middleware): self
    {
        if (!$middleware instanceof MiddlewareInterface && !is_callable($middleware)) {
            throw new \InvalidArgumentException('Route middleware must implement the PAM contract or be callable.');
        }
        $this->route->middleware[] = $middleware;
        return $this;
    }

    public function definition(): Route
    {
        return $this->route;
    }
}

