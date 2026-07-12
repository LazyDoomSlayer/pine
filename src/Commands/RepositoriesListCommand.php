<?php

declare(strict_types=1);

namespace Pine\Commands;

use Pine\Console\Command\AbstractCommand;
use Pine\Console\Definition\ArgumentDefinition;
use Pine\Console\Definition\CommandDefinition;
use Pine\Console\Definition\OptionDefinition;
use Pine\Console\Input;
use Pine\Console\JsonRenderer;
use Pine\Console\Output;
use Pine\Repositories\Repository;
use Pine\Repositories\RepositoryInspector;
use Pine\Repositories\RepositoryScanner;

final class RepositoriesListCommand extends AbstractCommand
{
    public function __construct(
        private readonly RepositoryScanner   $scanner,
        private readonly RepositoryInspector $inspector,
        private readonly JsonRenderer        $jsonRenderer,
    )
    {
    }

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
        $json = $input->hasOption('json');
        $path = $input->argument(0) ?? getcwd();
        $depth = (int)($input->option('depth') ?? 1);

        $repositories = $this->scanner->scan($path, $depth);
        $repositories = array_map(
            fn(Repository $repository): Repository => $this->inspector->inspect($repository),
            $repositories,
        );

        if ($repositories === []) {
            $output->warning('No Git repositories found.');

            return 0;
        }

        $sort = $input->option('sort');

        if (!is_string($sort)) {
            $sort = 'name';
        }

        usort(
            $repositories,
            match ($sort) {
                'modified' => static fn(
                    Repository $left,
                    Repository $right,
                ): int => ($right->lastCommitTimestamp ?? 0)
                    <=> ($left->lastCommitTimestamp ?? 0),

                'branch' => static fn(
                    Repository $left,
                    Repository $right,
                ): int => strcasecmp(
                    $left->branch ?? '',
                    $right->branch ?? '',
                ),

                default => static fn(
                    Repository $left,
                    Repository $right,
                ): int => strcasecmp(
                    $left->name,
                    $right->name,
                ),
            },
        );

        if ($json) {
            $data = array_map(
                static fn(Repository $repository): array => [
                    'name' => $repository->name,
                    'path' => $repository->path,
                    'branch' => $repository->branch,
                    'ahead' => $repository->ahead,
                    'behind' => $repository->behind,
                    'dirty' => $repository->dirty,
                    'lastCommitAt' => $repository->lastCommitTimestamp === null
                        ? null
                        : date(DATE_ATOM, $repository->lastCommitTimestamp),
                ],
                $repositories,
            );

            $output->line($this->jsonRenderer->render($data));
            return 0;
        }

        $rows = array_map(
            fn(Repository $repository): array => [
                $repository->name,
                $repository->branch ?? '—',
                $this->formatAheadBehind($repository, $output),
                $this->formatWorktree($repository, $output),
                self::formatModified($repository->lastCommitTimestamp),
                $repository->path,
            ],
            $repositories,
        );

        $output->table(
            headers: [
                'NAME',
                'BRANCH',
                'SYNC',
                'WORKTREE',
                'MODIFIED',
                'PATH',
            ],
            rows: $rows,
            numbered: true,
            title: 'Repositories',
            footer: sprintf(
                '%d repositories found.',
                count($rows),
            ),
        );

        return 0;
    }

    private function formatAheadBehind(
        Repository $repository,
        Output     $output,
    ): string
    {
        if ($repository->ahead === null || $repository->behind === null) {
            return $output->mutedText('no upstream');
        }

        if ($repository->ahead === 0 && $repository->behind === 0) {
            return $output->successText('synced');
        }

        if ($repository->ahead > 0 && $repository->behind > 0) {
            return $output->errorText(sprintf(
                '↑%d ↓%d',
                $repository->ahead,
                $repository->behind,
            ));
        }

        return $output->warningText(sprintf(
            '↑%d ↓%d',
            $repository->ahead,
            $repository->behind,
        ));
    }

    private function formatWorktree(
        Repository $repository,
        Output     $output,
    ): string
    {
        return match ($repository->dirty) {
            true => $output->warningText('dirty'),
            false => $output->successText('clean'),
            null => $output->mutedText('unknown'),
        };
    }

    private static function formatModified(?int $timestamp): string
    {
        if ($timestamp === null) {
            return '—';
        }

        return date('Y-m-d H:i', $timestamp);
    }

    protected function configure(): CommandDefinition
    {
        return new CommandDefinition(
            name: 'repos:list',
            description: 'Discover Git repositories inside a directory.',
            arguments: [
                new ArgumentDefinition(
                    name: 'path',
                    description: 'Directory in which repository scanning begins.',
                    required: false,
                    default: '.',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'depth',
                    description: 'Maximum directory scanning depth.',
                    acceptsValue: true,
                    default: '2',
                    valueName: 'LEVEL',
                ),
                new OptionDefinition(
                    name: 'json',
                    description: 'Output discovered repositories as JSON.',
                ),
            ],
            examples: [
                'pine repos:list',
                'pine repos:list ~/Projects',
                'pine repos:list ~/Projects --depth=3',
                'pine repos:list ~/Projects --json',
            ],

        );

    }
}

