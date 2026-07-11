<?php

declare(strict_types=1);

namespace Pine\Commands;

use Pine\Console\Command;
use Pine\Console\Input;
use Pine\Console\JsonRenderer;
use Pine\Console\Output;
use Pine\Repositories\Repository;
use Pine\Repositories\RepositoryInspector;
use Pine\Repositories\RepositoryScanner;

final class RepositoriesStatusCommand extends Command
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
        return 'repos:status';
    }

    public function getDescription(): string
    {
        return 'Show repositories that require attention.';
    }

    public function execute(Input $input, Output $output): int
    {
        $path = $input->argument(0) ?? getcwd();
        $depth = (int)($input->option('depth') ?? 1);
        $json = $input->hasOption('json');

        $repositories = $this->scanner->scan(
            directory: $path,
            depth: $depth,
        );

        $repositories = array_map(
            fn(Repository $repository): Repository => $this->inspector->inspect($repository),
            $repositories,
        );

        $repositories = array_values(array_filter(
            $repositories,
            fn(Repository $repository): bool => $this->requiresAttention($repository),
        ));

        usort(
            $repositories,
            static fn(
                Repository $left,
                Repository $right,
            ): int => strcasecmp(
                $left->name,
                $right->name,
            ),
        );

        if ($json) {
            $this->renderJson($repositories, $output);

            return 0;
        }

        $this->renderTable($repositories, $output);

        return 0;
    }

    private function requiresAttention(Repository $repository): bool
    {
        return $repository->dirty === true
            || ($repository->ahead ?? 0) > 0
            || ($repository->behind ?? 0) > 0
            || $repository->ahead === null
            || $repository->behind === null;
    }

    /**
     * @param list<Repository> $repositories
     */
    private function renderJson(
        array  $repositories,
        Output $output,
    ): void
    {
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
                    : date(
                        DATE_ATOM,
                        $repository->lastCommitTimestamp,
                    ),
            ],
            $repositories,
        );

        $output->line(
            $this->jsonRenderer->render($data),
        );
    }

    /**
     * @param list<Repository> $repositories
     */
    private function renderTable(
        array  $repositories,
        Output $output,
    ): void
    {
        if ($repositories === []) {
            $output->success(
                'All repositories are clean and synchronized.',
            );

            return;
        }

        $rows = array_map(
            fn(Repository $repository): array => [
                $repository->name,
                $repository->branch ?? '—',
                $this->formatAheadBehind($repository, $output),
                $this->formatWorktree($repository, $output),
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
                'PATH',
            ],
            rows: $rows,
            numbered: true,
            title: 'Repository Status',
            footer: sprintf(
                '%d %s require attention.',
                count($repositories),
                count($repositories) === 1
                    ? 'repository'
                    : 'repositories',
            ),
        );
    }

    private function formatAheadBehind(
        Repository $repository,
        Output     $output,
    ): string
    {
        if (
            $repository->ahead === null
            || $repository->behind === null
        ) {
            return $output->mutedText('no upstream');
        }

        if (
            $repository->ahead === 0
            && $repository->behind === 0
        ) {
            return $output->successText('synced');
        }

        if (
            $repository->ahead > 0
            && $repository->behind > 0
        ) {
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
}
