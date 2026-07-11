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

    public function run(Input $input): int
    {
        $commandName = $input->commandName();

        if ($commandName === null) {
            $this->helpRenderer->render($this->commands->all());

            return 0;
        }

        $command = $this->commands->find($commandName);

        if ($command !== null) {
            return $command->execute($input);
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
