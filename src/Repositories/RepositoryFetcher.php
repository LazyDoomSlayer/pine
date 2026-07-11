<?php

declare(strict_types=1);

namespace Pine\Repositories;

use Pine\Process\ProcessRunner;

final readonly class RepositoryFetcher
{
    private const MAX_MESSAGE_LENGTH = 100;

    public function __construct(
        private ProcessRunner $processRunner,
    )
    {
    }

    public function fetch(Repository $repository): FetchResult
    {
        $result = $this->processRunner->run(
            command: [
                'git',
                '-C',
                $repository->path,
                'fetch',
                '--prune',
            ],
            environment: [
                'GIT_TERMINAL_PROMPT' => '0',
                'GIT_SSH_COMMAND' => 'ssh -o BatchMode=yes',
            ],
        );

        return new FetchResult(
            repository: $repository,
            successful: $result->successful(),
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
        if (trim($output) === '') {
            return $successful
                ? 'fetched'
                : 'fetch failed';
        }

        if ($successful) {
            return $this->formatSuccessfulMessage($output);
        }

        return $this->formatFailureMessage($output);
    }

    private function formatSuccessfulMessage(string $output): string
    {
        $lines = $this->lines($output);

        $updatedReferences = count(array_filter(
            $lines,
            static fn(string $line): bool => str_contains($line, '->'),
        ));

        if ($updatedReferences > 0) {
            return sprintf(
                '%d %s updated',
                $updatedReferences,
                $updatedReferences === 1
                    ? 'reference'
                    : 'references',
            );
        }

        return 'fetched';
    }

    /**
     * @return list<string>
     */
    private function lines(string $output): array
    {
        $lines = preg_split('/\R/', trim($output));

        if ($lines === false) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn(string $line): string => trim($line),
                $lines,
            ),
            static fn(string $line): bool => $line !== '',
        ));
    }

    private function formatFailureMessage(string $output): string
    {
        $lines = $this->lines($output);

        $message = $lines[0] ?? 'fetch failed';

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
