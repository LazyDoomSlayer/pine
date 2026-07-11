<?php

declare(strict_types=1);

namespace Pine\Console;

final class Application
{
    public function __construct(
        private readonly CommandRegistry $commands,
        private readonly ApplicationHelpRenderer $helpRenderer,
    ) {
    }

    /**
     * @param array<int, string> $arguments
     */
    public function run(array $arguments): int
    {
        $commandName = $arguments[1] ?? null;

        if ($commandName === null) {
            $this->helpRenderer->render($this->commands->all());

            return 0;
        }

        $command = $this->commands->find($commandName);

        if ($command !== null) {
            return $command->execute();
        }

        fwrite(
            STDERR,
            sprintf(
                'Command "%s" was not found.%s',
                $commandName,
                PHP_EOL,
            ),
        );

        return 1;
    }
}
