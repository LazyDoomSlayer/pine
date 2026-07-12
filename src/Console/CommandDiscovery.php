<?php

declare(strict_types=1);

namespace Pine\Console;

use FilesystemIterator;
use Pine\Console\Command\Command;
use Pine\Container\Container;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;

final readonly class CommandDiscovery
{
    public function __construct(
        private Container $container,
    )
    {
    }

    public function discover(
        string $directory,
        string $namespace,
    ): CommandRegistry
    {
        if (!is_dir($directory)) {
            throw new RuntimeException(sprintf(
                'Command directory "%s" does not exist.',
                $directory,
            ));
        }

        $registry = new CommandRegistry();

        foreach ($this->discoverClassNames($directory, $namespace) as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            if (
                $reflection->isAbstract()
                || !$reflection->implementsInterface(Command::class)
            ) {
                continue;
            }

            $command = $this->container->get($className);

            if (!$command instanceof Command) {
                throw new RuntimeException(sprintf(
                    'Discovered class "%s" is not a command.',
                    $className,
                ));
            }

            $registry->register($command);
        }

        return $registry;
    }

    /**
     * @return list<class-string>
     */
    private function discoverClassNames(
        string $directory,
        string $namespace,
    ): array
    {
        $directory = rtrim(
            $directory,
            DIRECTORY_SEPARATOR,
        );

        $namespace = trim($namespace, '\\');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS,
            ),
        );

        $classNames = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (
                !$file->isFile()
                || $file->getExtension() !== 'php'
            ) {
                continue;
            }

            $relativePath = substr(
                $file->getPathname(),
                strlen($directory) + 1,
            );

            if ($relativePath === false) {
                continue;
            }

            $classPath = substr(
                $relativePath,
                0,
                -strlen('.php'),
            );

            $className = $namespace
                . '\\'
                . str_replace(
                    DIRECTORY_SEPARATOR,
                    '\\',
                    $classPath,
                );

            /** @var class-string $className */
            $classNames[] = $className;
        }

        sort($classNames);

        return $classNames;
    }
}
