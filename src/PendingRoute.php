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

    /** @param MiddlewareInterface|callable|class-string<MiddlewareInterface> $middleware */
    public function middleware(object|callable|string $middleware): self
    {
        if (is_string($middleware)) {
            if (is_a($middleware, MiddlewareInterface::class, true)) {
                $middleware = new ContainerMiddleware($this->container(), $middleware);
            }
        }
        if (is_object($middleware) && !$middleware instanceof MiddlewareInterface && !method_exists($middleware, '__invoke')) {
            throw new \InvalidArgumentException('Route middleware must implement the PAM contract or be callable.');
        }
        $this->route->middleware[] = $middleware;
        return $this;
    }

    private function container(): \Pam\Api\Container\Container
    {
        return $this->router->container();
    }

    public function definition(): Route
    {
        return $this->route;
    }

    public function summary(string $summary): self
    {
        if ($summary === '') {
            throw new \InvalidArgumentException('Route summary cannot be empty.');
        }
        $this->route->summary = $summary;
        return $this;
    }

    /** @param list<string> $tags */
    public function tags(array $tags): self
    {
        if ($tags === [] || array_filter($tags, static fn (string $tag): bool => $tag === '') !== []) {
            throw new \InvalidArgumentException('Route tags must be non-empty strings.');
        }
        $this->route->tags = array_values(array_unique($tags));
        return $this;
    }

    /** @param class-string $request */
    public function input(string $request): self
    {
        if (!class_exists($request)) {
            throw new \InvalidArgumentException("Input class {$request} does not exist.");
        }
        $this->route->input = $request;
        return $this;
    }

    /** @param class-string $resource */
    public function output(string $resource): self
    {
        if (!class_exists($resource)) {
            throw new \InvalidArgumentException("Output class {$resource} does not exist.");
        }
        $this->route->output = $resource;
        return $this;
    }
}
