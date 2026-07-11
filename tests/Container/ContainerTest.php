<?php

declare(strict_types=1);

namespace Pine\Tests\Container;

use PHPUnit\Framework\TestCase;
use Pine\Container\Container;
use RuntimeException;

interface ServiceContract
{
}

final class ContainerTest extends TestCase
{
    public function testItResolvesClassWithoutConstructor(): void
    {
        $container = new Container();

        $service = $container->get(SimpleService::class);

        self::assertInstanceOf(SimpleService::class, $service);
    }

    public function testItAutomaticallyResolvesConstructorDependencies(): void
    {
        $container = new Container();

        $service = $container->get(ServiceWithDependency::class);

        self::assertInstanceOf(ServiceWithDependency::class, $service);
        self::assertInstanceOf(SimpleService::class, $service->dependency);
    }

    public function testItReturnsRegisteredInstance(): void
    {
        $container = new Container();
        $service = new SimpleService();

        $container->instance(SimpleService::class, $service);

        $resolvedService = $container->get(SimpleService::class);

        self::assertSame($service, $resolvedService);
    }

    public function testItResolvesExplicitBinding(): void
    {
        $container = new Container();
        $service = new AlternativeService();

        $container->bind(
            ServiceContract::class,
            static fn(Container $container): object => $service,
        );

        $resolvedService = $container->get(ServiceContract::class);

        self::assertSame($service, $resolvedService);
    }

    public function testItCanUseContainerInsideBindingFactory(): void
    {
        $container = new Container();

        $container->bind(
            ServiceContract::class,
            static fn(Container $container): object => new DependentService(
                dependency: $container->get(SimpleService::class),
            ),
        );

        $service = $container->get(ServiceContract::class);

        self::assertInstanceOf(DependentService::class, $service);
        self::assertInstanceOf(SimpleService::class, $service->dependency);
    }

    public function testItFailsWhenConstructorContainsScalarParameter(): void
    {
        $container = new Container();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Unable to resolve parameter "$name"',
        );

        $container->get(ServiceWithScalarDependency::class);
    }

    public function testItFailsWhenClassIsNotInstantiable(): void
    {
        $container = new Container();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Class "%s" is not instantiable.',
                ServiceContract::class,
            ),
        );

        $container->get(ServiceContract::class);
    }

    public function testBindingsCreateNewInstanceEveryTime(): void
    {
        $container = new Container();

        $container->bind(
            SimpleService::class,
            static fn(Container $container): object => new SimpleService(),
        );

        $first = $container->get(SimpleService::class);
        $second = $container->get(SimpleService::class);

        self::assertNotSame($first, $second);
    }

    public function testItDetectsCircularDependencies(): void
    {
        $container = new Container();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Circular dependency detected: %s -> %s -> %s',
                CircularDependencyA::class,
                CircularDependencyB::class,
                CircularDependencyA::class,
            ),
        );

        $container->get(CircularDependencyA::class);
    }
}

final readonly class CircularDependencyA
{
    public function __construct(
        public CircularDependencyB $dependency,
    )
    {
    }
}

final readonly class CircularDependencyB
{
    public function __construct(
        public CircularDependencyA $dependency,
    )
    {
    }
}

final class SimpleService
{
}

final readonly class ServiceWithDependency
{
    public function __construct(
        public SimpleService $dependency,
    )
    {
    }
}

final class AlternativeService implements ServiceContract
{
}

final readonly class DependentService implements ServiceContract
{
    public function __construct(
        public SimpleService $dependency,
    )
    {
    }
}

final readonly class ServiceWithScalarDependency
{
    public function __construct(
        public string $name,
    )
    {
    }
}
