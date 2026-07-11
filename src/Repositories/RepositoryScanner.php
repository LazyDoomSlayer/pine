<?php

declare(strict_types=1);

namespace Pine\Repositories;

use FilesystemIterator;
use InvalidArgumentException;

final class RepositoryScanner
{
    private const array IGNORED_DIRECTORIES = [
        '.git',
        '.idea',
        '.vscode',
        '.vs',

        'vendor',
        'node_modules',
        'bower_components',
        'jspm_packages',

        'target',
        'build',
        'dist',
        'out',
        'bin',
        'obj',

        '.gradle',
        '.mvn',

        '__pycache__',
        '.pytest_cache',
        '.mypy_cache',
        '.ruff_cache',
        '.tox',
        '.venv',
        'venv',
        'env',

        '.dart_tool',
        '.pub-cache',

        '.next',
        '.nuxt',
        '.svelte-kit',
        '.angular',
        '.turbo',

        '.cache',
        '.parcel-cache',

        '.terraform',

        'Pods',
        'DerivedData',

        '.cargo',

        '.zig-cache',

        '.scannerwork',

        'coverage',
        '.coverage',

        '.DS_Store',
    ];

    /**
     * @return list<Repository>
     */
    public function scan(
        string $directory,
        int    $depth = 1,
    ): array
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException(sprintf(
                'Directory "%s" does not exist.',
                $directory,
            ));
        }

        if ($depth < 0) {
            throw new InvalidArgumentException(
                'Depth cannot be negative.',
            );
        }

        $repositories = [];

        $resolvedDirectory = realpath($directory);

        if ($resolvedDirectory === false) {
            throw new InvalidArgumentException(sprintf(
                'Directory "%s" could not be resolved.',
                $directory,
            ));
        }

        $this->scanDirectory(
            directory: $resolvedDirectory,
            currentDepth: 0,
            maxDepth: $depth,
            repositories: $repositories,
        );

        usort(
            $repositories,
            static fn(Repository $left, Repository $right): int => strcasecmp(
                $left->name,
                $right->name,
            ),
        );

        return $repositories;
    }

    /**
     * @param list<Repository> $repositories
     */
    private function scanDirectory(
        string $directory,
        int    $currentDepth,
        int    $maxDepth,
        array  &$repositories,
    ): void
    {
        $iterator = new FilesystemIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS,
        );

        foreach ($iterator as $item) {
            if (!$item->isDir() || $item->isLink()) {
                continue;
            }

            if ($this->shouldSkipDirectory($item->getFilename())) {
                continue;
            }

            $gitDirectory = $item->getPathname() . DIRECTORY_SEPARATOR . '.git';

            if (is_dir($gitDirectory)) {
                $repositories[] = new Repository(
                    name: $item->getFilename(),
                    path: $item->getPathname(),
                );

                continue;
            }

            if ($currentDepth >= $maxDepth) {
                continue;
            }

            $this->scanDirectory(
                directory: $item->getPathname(),
                currentDepth: $currentDepth + 1,
                maxDepth: $maxDepth,
                repositories: $repositories,
            );
        }
    }

    private function shouldSkipDirectory(string $directory): bool
    {
        return in_array(
            $directory,
            self::IGNORED_DIRECTORIES,
            true,
        );
    }
}
