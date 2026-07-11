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

        $rows = array_map(
            static fn($repository): array => [
                $repository->name,
                $repository->path,
            ],
            $repositories,
        );

        $output->table(
            headers: ['NAME', 'PATH'],
            rows: $rows,
            numbered: true,
            title: 'Repositories',
            footer: sprintf('%d repositories found.', count($rows)),
        );
        
        return 0;
    }
}

