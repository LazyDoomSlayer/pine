<?php

declare(strict_types=1);

namespace Pine\Commands;

use Pine\Console\Command;
use Pine\Console\Input;
use Pine\Console\Output;
use Pine\Repositories\RepositoryScanner;

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
        $depth = (int)($input->option('depth') ?? 1);
        $scanner = new RepositoryScanner();
        $repositories = $scanner->scan($path, $depth);

        if ($repositories === []) {
            $output->warning('No Git repositories found.');

            return 0;
        }

        foreach ($repositories as $repository) {
            $output->line(sprintf(
                '%s  %s',
                $repository->name,
                $repository->path,
            ));

        }

        return 0;
    }
}

