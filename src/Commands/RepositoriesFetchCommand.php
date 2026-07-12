<?php

declare(strict_types=1);

namespace Pine\Commands;

use Pine\Console\Command\AbstractCommand;
use Pine\Console\Definition\ArgumentDefinition;
use Pine\Console\Definition\CommandDefinition;
use Pine\Console\Definition\OptionDefinition;
use Pine\Console\Input;
use Pine\Console\Output;
use Pine\Repositories\FetchResult;
use Pine\Repositories\Repository;
use Pine\Repositories\RepositoryFetcher;
use Pine\Repositories\RepositoryInspector;
use Pine\Repositories\RepositoryScanner;

final class RepositoriesFetchCommand extends AbstractCommand
{
    public function __construct(
        private RepositoryScanner   $scanner,
        private RepositoryInspector $inspector,
        private RepositoryFetcher   $fetcher,
    )
    {
    }

    public function getName(): string
    {
        return 'repos:fetch';
    }

    public function getDescription(): string
    {
        return 'Fetch updates for discovered Git repositories.';
    }

    public function execute(Input $input, Output $output): int
    {
        $path = $input->argument(0) ?? getcwd();
        $depth = (int)($input->option('depth') ?? 1);

        $repositories = $this->scanner->scan(
            directory: $path,
            depth: $depth,
        );

        $repositories = array_map(
            fn(Repository $repository): Repository => $this->inspector->inspect($repository),
            $repositories,
        );

        if ($repositories === []) {
            $output->warning('No Git repositories found.');

            return 0;
        }

        $previewRows = array_map(
            static fn(Repository $repository): array => [
                $repository->name,
                $repository->branch ?? '—',
                $repository->path,
            ],
            $repositories,
        );

        $output->table(
            headers: ['NAME', 'BRANCH', 'PATH'],
            rows: $previewRows,
            numbered: true,
            title: 'Repositories to Fetch',
            footer: sprintf(
                '%d repositories selected.',
                count($repositories),
            ),
        );

        $confirmed = $input->hasOption('yes')
            || $input->confirm('Continue with fetch?');

        if (!$confirmed) {
            $output->warning('Fetch cancelled.');

            return 0;
        }

        $results = array_map(
            fn(Repository $repository): FetchResult => $this->fetcher->fetch($repository),
            $repositories,
        );

        $rows = array_map(
            fn(FetchResult $result): array => [
                $result->repository->name,
                $result->successful
                    ? $output->successText('success')
                    : $output->errorText('failed'),
                $result->message,
            ],
            $results,
        );

        $failed = count(array_filter(
            $results,
            static fn(FetchResult $result): bool => !$result->successful,
        ));

        $output->table(
            headers: ['NAME', 'RESULT', 'MESSAGE'],
            rows: $rows,
            numbered: true,
            title: 'Fetch Results',
            footer: sprintf(
                '%d processed, %d failed.',
                count($results),
                $failed,
            ),
        );

        return $failed === 0 ? 0 : 1;
    }

    protected function configure(): CommandDefinition
    {
        return new CommandDefinition(
            name: 'repos:fetch',
            description: 'Fetch updates for discovered Git repositories.',
            arguments: [
                new ArgumentDefinition(
                    name: 'path',
                    description: 'Directory in which repository scanning begins.',
                    default: '.',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'depth',
                    description: 'Maximum directory scanning depth.',
                    acceptsValue: true,
                    default: '1',
                    valueName: 'LEVEL',
                ),
                new OptionDefinition(
                    name: 'yes',
                    description: 'Skip the confirmation prompt.',
                    shortcut: 'y',
                ),
            ],
            examples: [
                'pine repos:fetch',
                'pine repos:fetch ~/Projects',
                'pine repos:fetch ~/Projects --depth=3',
                'pine repos:fetch ~/Projects --yes',
            ],
        );
    }
}
