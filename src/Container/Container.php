<?php

declare(strict_types=1);

namespace Pine\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use RuntimeException;

final class Container
{
    /**
     * @var array<string, Closure(self): object>
     */
    private array $bindings = [];

    /**
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * @var list<string>
     */
    private array $resolving = [];

    /**
     * @param Closure(self): object $factory
     */
    public function bind(string $id, Closure $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $this->guardAgainstCircularDependency($id);

        $this->resolving[] = $id;

        try {
            if (isset($this->bindings[$id])) {
                return ($this->bindings[$id])($this);
            }

            return $this->build($id);
        } finally {
            array_pop($this->resolving);
        }
    }

    private function guardAgainstCircularDependency(string $id): void
    {
        if (!in_array($id, $this->resolving, true)) {
            return;
        }

        $cycleStart = array_search(
            $id,
            $this->resolving,
            true,
        );

        $cycle = array_slice(
            $this->resolving,
            $cycleStart === false ? 0 : $cycleStart,
        );

        $cycle[] = $id;

        throw new RuntimeException(sprintf(
            'Circular dependency detected: %s',
            implode(' -> ', $cycle),
        ));
    }

    private function build(string $id): object
    {
        try {
            $reflection = new ReflectionClass($id);
        } catch (ReflectionException $exception) {
            throw new RuntimeException(
                sprintf('Unable to resolve "%s".', $id),
                previous: $exception,
            );
        }

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException(
                sprintf('Class "%s" is not instantiable.', $id),
            );
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $id();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                throw new RuntimeException(sprintf(
                    'Unable to resolve parameter "$%s" for "%s".',
                    $parameter->getName(),
                    $id,
                ));
            }

            $dependencies[] = $this->get($type->getName());
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}
