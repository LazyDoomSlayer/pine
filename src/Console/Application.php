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

        var_dump($commandName);

        if ($commandName === 'repos:list') {
            $command = new RepositoriesListCommand();

            return $command->execute();
        }


        return 0;
    }
}
