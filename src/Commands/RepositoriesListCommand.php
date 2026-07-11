<?php

declare(strict_types=1);

namespace Pine\Commands;

use Pine\Console\Command;

final class RepositoriesListCommand extends Command
{
    public function execute(): int
    {
        echo 'Listing repositories...' . PHP_EOL;

        return 0;
    }
}
