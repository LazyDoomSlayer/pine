<?php

declare(strict_types=1);

namespace Pine\Tests\Support;

use Pine\Process\ProcessResult;
use Pine\Process\ProcessRunnerInterface;

final class FakeProcessRunner implements ProcessRunnerInterface
{
    /**
     * @var list<string>
     */
    public array $lastCommand = [];

    /**
     * @param array<string, string> $environment
     */
    public function __construct(
        private readonly ProcessResult $result,
    )
    {
    }

    public function run(
        array $command,
        array $environment = [],
    ): ProcessResult
    {
        $this->lastCommand = $command;

        return $this->result;
    }
}
