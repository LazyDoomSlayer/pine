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

        $command = new RepositoriesListCommand();

        if ($command->getName() === $commandName) {
            return $command->execute();
        }

        fwrite(STDERR, sprintf(
            'Command "%s" was not found.%s',
            $commandName ?? '',
            PHP_EOL,
        ));

        return 1;
    }
}
