<?php

declare(strict_types=1);

namespace Pine\Commands;

use Pine\Console\Command;
use Pine\Console\Input;
use Pine\Console\Output;

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

    public function execute(Input $input, Output $output): int
    {

        $path = $input->argument(0) ?? getcwd();
        $json = $input->hasOption('json');
        $depth = (int)($input->option('depth') ?? 1);

        $output->info('Repository scan configuration');
        $output->line("Path: {$path}");
        $output->line('JSON: ' . ($json ? 'yes' : 'no'));
        $output->line("Depth: {$depth}");

        return 0;
    }
}

