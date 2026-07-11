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

        if (isset($this->bindings[$id])) {
            return ($this->bindings[$id])($this);
        }

        return $this->build($id);
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
