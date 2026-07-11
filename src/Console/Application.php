<?php

declare(strict_types=1);

namespace Pine\Console;

use Pine\Commands\RepositoriesListCommand;

final class Application
{
    /**
     * @param array<int, string> $arguments
     */
    public function run(array $arguments): int
    {
        $commandName = $arguments[1] ?? null;

        if ($commandName === null) {
            $this->renderHelp();

            return 0;
        }

        $command = new RepositoriesListCommand();

        if ($command->getName() === $commandName) {
            return $command->execute();
        }

        fwrite(STDERR, sprintf(
            'Command "%s" was not found.%s',
            $commandName,
            PHP_EOL,
        ));

        return 1;
    }

    private function renderHelp(): void
    {
        echo <<<'TEXT'
        Pine CLI

        Usage:
          pine <command>

        Available commands:
          repos:list    List Git repositories

        TEXT;

        echo PHP_EOL;
    }
}
