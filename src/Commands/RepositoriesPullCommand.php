<?php

declare(strict_types=1);

namespace Pine\Commands;

use Pine\Console\Command\AbstractCommand;
use Pine\Console\Definition\ArgumentDefinition;
use Pine\Console\Definition\CommandDefinition;
use Pine\Console\Definition\OptionDefinition;
use Pine\Console\Input;
use Pine\Console\Output;
use Pine\Repositories\PullResult;
use Pine\Repositories\Repository;
use Pine\Repositories\RepositoryInspector;
use Pine\Repositories\RepositoryPuller;
use Pine\Repositories\RepositoryScanner;

final class RepositoriesPullCommand extends AbstractCommand
{
    public function __construct(
        private RepositoryScanner   $scanner,
        private RepositoryInspector $inspector,
        private RepositoryPuller    $puller,
    )
    {
    }

    public function getName(): string
    {
        return 'repos:pull';
    }

    public function getDescription(): string
    {
        return 'Pull updates for discovered Git repositories.';
    }

    public function execute(Input $input, Output $output): int
    {
        $path = $input->argument(0) ?? getcwd();
        $depth = (int)($input->option('depth') ?? 1);
        $includeDirty = $input->hasOption('include-dirty');

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

        $output->table(
            headers: [
                'NAME',
                'BRANCH',
                'SYNC',
                'WORKTREE',
                'PATH',
            ],
            rows: array_map(
                fn(Repository $repository): array => [
                    $repository->name,
                    $repository->branch ?? '—',
                    $this->formatSync($repository, $output),
                    $this->formatWorktree($repository, $output),
                    $repository->path,
                ],
                $repositories,
            ),
            numbered: true,
            title: 'Repositories to Pull',
            footer: sprintf(
                '%d repositories selected.',
                count($repositories),
            ),
        );

        $confirmed = $input->hasOption('yes')
            || $input->confirm('Continue with pull?');

        if (!$confirmed) {
            $output->warning('Pull cancelled.');

            return 0;
        }

        $results = array_map(
            fn(Repository $repository): PullResult => $this->puller->pull(
                repository: $repository,
                includeDirty: $includeDirty,
            ),
            $repositories,
        );

        $output->table(
            headers: [
                'NAME',
                'RESULT',
                'MESSAGE',
            ],
            rows: array_map(
                fn(PullResult $result): array => [
                    $result->repository->name,
                    $this->formatResult($result, $output),
                    $result->message,
                ],
                $results,
            ),
            numbered: true,
            title: 'Pull Results',
            footer: $this->formatFooter($results),
        );

        $failed = array_filter(
            $results,
            static fn(PullResult $result): bool => $result->failed(),
        );

        return $failed === [] ? 0 : 1;
    }

    private function formatSync(
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

    private function formatResult(
        PullResult $result,
        Output     $output,
    ): string
    {
        return match ($result->status) {
            'success' => $output->successText('success'),
            'skipped' => $output->warningText('skipped'),
            default => $output->errorText('failed'),
        };
    }

    /**
     * @param list<PullResult> $results
     */
    private function formatFooter(array $results): string
    {
        $failed = count(array_filter(
            $results,
            static fn(PullResult $result): bool => $result->failed(),
        ));

        $skipped = count(array_filter(
            $results,
            static fn(PullResult $result): bool => $result->skipped(),
        ));

        return sprintf(
            '%d processed, %d skipped, %d failed.',
            count($results),
            $skipped,
            $failed,
        );
    }

    protected function configure(): CommandDefinition
    {
        return new CommandDefinition(
            name: 'repos:pull',
            description: 'Pull the latest changes for discovered Git repositories.',
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
                    default: '2',
                    valueName: 'LEVEL',
                ),
                new OptionDefinition(
                    name: 'yes',
                    description: 'Skip the confirmation prompt.',
                    shortcut: 'y',
                ),
            ],
            examples: [
                'pine repos:pull',
                'pine repos:pull ~/Projects',
                'pine repos:pull ~/Projects --depth=3',
                'pine repos:pull ~/Projects --yes',
            ],
        );
    }
}
