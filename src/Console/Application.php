<?php

declare(strict_types=1);

namespace Pine\Console;

final class Application
{
    public function __construct(
        private readonly CommandRegistry         $commands,
        private readonly ApplicationHelpRenderer $helpRenderer,
        private readonly Output                  $output,
    )
    {
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
            return $command->execute($input, $this->output);
        }

  
        $this->output->error(
            sprintf('Command "%s" was not found.', $commandName),
        );

        return 1;
    }
}

