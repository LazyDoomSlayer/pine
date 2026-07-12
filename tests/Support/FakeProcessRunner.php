<?php

declare(strict_types=1);

namespace Pine\Tests\Support;

use Pine\Process\ProcessResult;
use Pine\Process\ProcessRunnerInterface;
use RuntimeException;

final class FakeProcessRunner implements ProcessRunnerInterface
{
    /**
     * @var list<string>
     */
    public array $lastCommand = [];

    /**
     * @var list<list<string>>
     */
    public array $commands = [];

    /**
     * @var list<ProcessResult>
     */
    private array $results;

    public function __construct(
        ProcessResult ...$results,
    )
    {
        $this->results = $results;
    }

    public function run(
        array $command,
        array $environment = [],
    ): ProcessResult
    {
        $this->lastCommand = $command;
        $this->commands[] = $command;

        $result = array_shift($this->results);

        if (!$result instanceof ProcessResult) {
            throw new RuntimeException(
                'No fake process result was configured.',
            );
        }

        return $result;
    }
}
