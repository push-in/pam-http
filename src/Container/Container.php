<?php

declare(strict_types=1);

namespace Pam\Api\Container;

final class Container
{
    /** @var array<class-string|string, Binding> */
    private array $bindings = [];

    /** @var array<class-string|string, mixed> */
    private array $singletons = [];

    /** @var array<class-string|string, mixed> */
    private array $scoped = [];

    private bool $scopeActive = false;

    /** @param class-string|string $id */
    public function bind(string $id, callable|string|null $factory = null): self
    {
        return $this->register($id, $factory, BindingLifetime::Transient);
    }

    /** @param class-string|string $id */
    public function singleton(string $id, callable|string|null $factory = null): self
    {
        return $this->register($id, $factory, BindingLifetime::Singleton);
    }

    /** @param class-string|string $id */
    public function scoped(string $id, callable|string|null $factory = null): self
    {
        return $this->register($id, $factory, BindingLifetime::Scoped);
    }

    public function instance(string $id, mixed $instance): self
    {
        $this->singletons[$id] = $instance;
        return $this;
    }

    public function scopedInstance(string $id, mixed $instance): self
    {
        if (!$this->scopeActive) {
            throw new \LogicException("Scoped entry {$id} cannot be registered outside a request scope.");
        }
        $this->scoped[$id] = $instance;
        return $this;
    }

    public function beginScope(): void
    {
        if ($this->scopeActive) {
            throw new \LogicException('A PAM API container scope is already active.');
        }
        $this->scoped = [];
        $this->scopeActive = true;
    }

    public function endScope(): void
    {
        $this->scoped = [];
        $this->scopeActive = false;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->singletons)) {
            return $this->singletons[$id];
        }
        if (array_key_exists($id, $this->scoped)) {
            return $this->scoped[$id];
        }

        $binding = $this->bindings[$id] ?? null;
        if ($binding === null) {
            if (!class_exists($id)) {
                throw new \RuntimeException("Container entry {$id} is not bound and is not a class.");
            }
            return $this->build($id);
        }

        $value = ($binding->factory)($this);
        if ($binding->lifetime === BindingLifetime::Singleton) {
            $this->singletons[$id] = $value;
        } elseif ($binding->lifetime === BindingLifetime::Scoped) {
            if (!$this->scopeActive) {
                throw new \LogicException("Scoped entry {$id} was resolved outside a request scope.");
            }
            $this->scoped[$id] = $value;
        }
        return $value;
    }

    /**
     * @param array{object, string} $callable
     * @param array<string, mixed> $named
     * @param list<mixed> $provided
     */
    public function call(array $callable, array $named = [], array $provided = []): mixed
    {
        $reflection = new \ReflectionMethod($callable[0], $callable[1]);
        $arguments = $this->resolveParameters($reflection->getParameters(), $named, $provided);
        return $reflection->invokeArgs($callable[0], $arguments);
    }

    /** @param class-string $class */
    private function build(string $class): object
    {
        $reflection = new \ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new \RuntimeException("Container entry {$class} is not instantiable.");
        }
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $reflection->newInstance();
        }
        return $reflection->newInstanceArgs(
            $this->resolveParameters($constructor->getParameters()),
        );
    }

    /**
     * @param list<\ReflectionParameter> $parameters
     * @param array<string, mixed> $named
     * @param list<mixed> $provided
     * @return list<mixed>
     */
    private function resolveParameters(array $parameters, array $named = [], array $provided = []): array
    {
        $arguments = [];
        foreach ($parameters as $parameter) {
            if (array_key_exists($parameter->getName(), $named)) {
                $arguments[] = $named[$parameter->getName()];
                continue;
            }
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $class = $type->getName();
                $matched = null;
                foreach ($provided as $candidate) {
                    if ($candidate instanceof $class) {
                        $matched = $candidate;
                        break;
                    }
                }
                $arguments[] = $matched ?? $this->get($class);
                continue;
            }
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }
            if ($parameter->allowsNull()) {
                $arguments[] = null;
                continue;
            }
            throw new \RuntimeException(
                "Unable to resolve parameter \${$parameter->getName()} for {$parameter->getDeclaringFunction()->getName()}().",
            );
        }
        return $arguments;
    }

    private function register(
        string $id,
        callable|string|null $factory,
        BindingLifetime $lifetime,
    ): self {
        $resolver = match (true) {
            $factory === null => static function (self $container) use ($id): mixed {
                if (!class_exists($id)) {
                    throw new \RuntimeException("Container entry {$id} is not a class.");
                }
                return $container->build($id);
            },
            is_string($factory) => static fn (self $container): mixed => $container->get($factory),
            default => $factory,
        };
        $this->bindings[$id] = new Binding($resolver, $lifetime);
        unset($this->singletons[$id], $this->scoped[$id]);
        return $this;
    }
}
