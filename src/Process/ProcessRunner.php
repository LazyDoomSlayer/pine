<?php

declare(strict_types=1);

namespace Pine\Process;

final class ProcessRunner implements ProcessRunnerInterface
{
    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     */
    public function run(
        array $command,
        array $environment = [],
    ): ProcessResult
    {
        $parts = [];

        if ($environment !== []) {
            $parts[] = 'env';

            foreach ($environment as $name => $value) {
                $parts[] = sprintf(
                    '%s=%s',
                    $name,
                    $value,
                );
            }
        }

        $parts = [
            ...$parts,
            ...$command,
        ];

        $escapedCommand = implode(
            ' ',
            array_map(
                static fn(string $part): string => escapeshellarg($part),
                $parts,
            ),
        );

        $output = [];
        $exitCode = 0;

        exec(
            $escapedCommand . ' 2>&1',
            $output,
            $exitCode,
        );

        return new ProcessResult(
            exitCode: $exitCode,
            output: trim(implode(PHP_EOL, $output)),
        );
    }
}
