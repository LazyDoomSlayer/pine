<?php

declare(strict_types=1);

namespace Pine\Commands;

use Pine\Console\Command;
use Pine\Console\Input;

final class RepositoriesListCommand extends Command
{
    public function getName(): string
    {
        return 'repos:list';
    }

    public function getDescription(): string
    {
        return 'List Git repositories.';
    }

    public function execute(Input $input): int
    {
        var_dump($input->tokens());

        return 0;
    }
}
