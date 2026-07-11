<?php

declare(strict_types=1);

namespace Pine\Repositories;

use Pine\Process\ProcessRunner;

final readonly class RepositoryPuller
{
    private const MAX_MESSAGE_LENGTH = 100;

    public function __construct(
        private ProcessRunner $processRunner,
    )
    {
    }

    public function pull(
        Repository $repository,
        bool       $includeDirty = false,
    ): PullResult
    {
        if ($repository->branch === null) {
            return new PullResult(
                repository: $repository,
                status: 'skipped',
                message: 'detached HEAD',
            );
        }

        if (
            $repository->ahead === null
            || $repository->behind === null
        ) {
            return new PullResult(
                repository: $repository,
                status: 'skipped',
                message: 'no upstream',
            );
        }

        if ($repository->dirty === true && !$includeDirty) {
            return new PullResult(
                repository: $repository,
                status: 'skipped',
                message: 'dirty worktree',
            );
        }

        if (
            $repository->ahead > 0
            && $repository->behind > 0
        ) {
            return new PullResult(
                repository: $repository,
                status: 'skipped',
                message: 'branch has diverged',
            );
        }

        if ($repository->behind === 0) {
            return new PullResult(
                repository: $repository,
                status: 'success',
                message: 'already up to date',
            );
        }

        $result = $this->processRunner->run(
            command: [
                'git',
                '-C',
                $repository->path,
                'pull',
                '--ff-only',
            ],
            environment: [
                'GIT_TERMINAL_PROMPT' => '0',
                'GIT_SSH_COMMAND' => 'ssh -o BatchMode=yes',
            ],
        );

        return new PullResult(
            repository: $repository,
            status: $result->successful()
                ? 'success'
                : 'failed',
            message: $this->formatMessage(
                output: $result->output,
                successful: $result->successful(),
            ),
        );
    }

    private function formatMessage(
        string $output,
        bool   $successful,
    ): string
    {
        $lines = preg_split('/\R/', trim($output));

        if ($lines === false) {
            return $successful
                ? 'updated'
                : 'pull failed';
        }

        $lines = array_values(array_filter(
            array_map(
                static fn(string $line): string => trim($line),
                $lines,
            ),
            static fn(string $line): bool => $line !== '',
        ));

        if ($successful) {
            foreach ($lines as $line) {
                if (str_contains($line, 'Already up to date')) {
                    return 'already up to date';
                }

                if (str_contains($line, 'Fast-forward')) {
                    return 'fast-forwarded';
                }
            }

            return 'updated';
        }

        $message = $lines[0] ?? 'pull failed';

        if (mb_strlen($message) <= self::MAX_MESSAGE_LENGTH) {
            return $message;
        }

        return mb_substr(
                $message,
                0,
                self::MAX_MESSAGE_LENGTH - 1,
            ) . '…';
    }
}
