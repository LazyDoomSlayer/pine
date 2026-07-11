<?php

declare(strict_types=1);

namespace Pine\Repositories;

final class RepositoryInspector
{
    public function inspect(Repository $repository): Repository
    {
        $aheadBehind = $this->aheadBehind($repository->path);

        return new Repository(
            name: $repository->name,
            path: $repository->path,
            branch: $this->branch($repository->path),
            ahead: $aheadBehind['ahead'],
            behind: $aheadBehind['behind'],
            lastCommitTimestamp: $this->lastCommitTimestamp($repository->path),
            dirty: $this->isDirty($repository->path),
        );
    }

    /**
     * @return array{ahead: ?int, behind: ?int}
     */
    private function aheadBehind(string $repositoryPath): array
    {
        $result = $this->runGit(
            repositoryPath: $repositoryPath,
            arguments: [
                'rev-list',
                '--left-right',
                '--count',
                'HEAD...@{upstream}',
            ],
        );

        if ($result === null || $result === '') {
            return [
                'ahead' => null,
                'behind' => null,
            ];
        }

        $parts = preg_split('/\s+/', trim($result));

        if ($parts === false || count($parts) !== 2) {
            return [
                'ahead' => null,
                'behind' => null,
            ];
        }

        return [
            'ahead' => (int)$parts[0],
            'behind' => (int)$parts[1],
        ];
    }

    /**
     * @param list<string> $arguments
     */
    private function runGit(
        string $repositoryPath,
        array  $arguments,
    ): ?string
    {
        $command = [
            'git',
            '-C',
            $repositoryPath,
            ...$arguments,
        ];

        $escapedCommand = implode(
            ' ',
            array_map(
                static fn(string $part): string => escapeshellarg($part),
                $command,
            ),
        );

        $output = [];
        $exitCode = 0;

        exec(
            $escapedCommand . ' 2>/dev/null',
            $output,
            $exitCode,
        );

        if ($exitCode !== 0) {
            return null;
        }

        return trim(implode(PHP_EOL, $output));
    }

    private function branch(string $repositoryPath): ?string
    {
        $branch = $this->runGit(
            repositoryPath: $repositoryPath,
            arguments: ['branch', '--show-current'],
        );

        if ($branch !== null && $branch !== '') {
            return $branch;
        }

        // Detached HEAD.
        return $this->runGit(
            repositoryPath: $repositoryPath,
            arguments: ['rev-parse', '--short', 'HEAD'],
        );
    }

    private function lastCommitTimestamp(string $repositoryPath): ?int
    {
        $timestamp = $this->runGit(
            repositoryPath: $repositoryPath,
            arguments: ['log', '-1', '--format=%ct'],
        );

        if ($timestamp === null || !ctype_digit($timestamp)) {
            return null;
        }

        return (int)$timestamp;
    }

    private function isDirty(string $repositoryPath): ?bool
    {
        $status = $this->runGit(
            repositoryPath: $repositoryPath,
            arguments: [
                'status',
                '--porcelain',
            ],
        );

        if ($status === null) {
            return null;
        }

        return $status !== '';
    }
}
