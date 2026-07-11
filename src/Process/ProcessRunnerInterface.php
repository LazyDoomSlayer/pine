<?php

declare(strict_types=1);

namespace Pine\Process;

interface ProcessRunnerInterface
{
    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     */
    public function run(
        array $command,
        array $environment = [],
    ): ProcessResult;
}
