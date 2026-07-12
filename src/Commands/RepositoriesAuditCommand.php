<?php

declare(strict_types=1);

namespace Pine\Commands;

use Pine\Audit\AuditFinding;
use Pine\Audit\AuditManager;
use Pine\Audit\AuditResult;
use Pine\Console\Command\AbstractCommand;
use Pine\Console\Definition\ArgumentDefinition;
use Pine\Console\Definition\CommandDefinition;
use Pine\Console\Definition\OptionDefinition;
use Pine\Console\Input;
use Pine\Console\JsonRenderer;
use Pine\Console\Output;
use Pine\Repositories\Repository;
use Pine\Repositories\RepositoryScanner;

final class RepositoriesAuditCommand extends AbstractCommand
{
    public function __construct(
        private RepositoryScanner $scanner,
        private AuditManager      $auditManager,
        private JsonRenderer      $jsonRenderer,
    )
    {
    }

    public function getName(): string
    {
        return 'repos:audit';
    }

    public function getDescription(): string
    {
        return 'Audit repository dependencies for known vulnerabilities.';
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

        $supportedRepositories = array_values(array_filter(
            $repositories,
            fn(Repository $repository): bool => $this->auditManager->supports($repository),
        ));

        if ($supportedRepositories === []) {
            if ($json) {
                $output->line($this->jsonRenderer->render([
                    'repositories' => [],
                    'summary' => [
                        'audited' => 0,
                        'clean' => 0,
                        'vulnerable' => 0,
                        'failed' => 0,
                        'findings' => 0,
                    ],
                ]));

                return 0;
            }

            $output->warning(
                'No supported dependency repositories found.',
            );

            return 0;
        }

        if (!$json) {
            $this->renderPreview(
                repositories: $supportedRepositories,
                output: $output,
            );
        }

        $confirmed = $input->hasOption('yes')
            || $input->confirm('Continue with dependency audit?');

        if (!$confirmed) {
            if ($json) {
                $output->line($this->jsonRenderer->render([
                    'cancelled' => true,
                ]));
            } else {
                $output->warning('Dependency audit cancelled.');
            }

            return 0;
        }

        $results = [];
        $total = count($supportedRepositories);

        foreach ($supportedRepositories as $index => $repository) {
            $position = $index + 1;
            $ecosystem = $this->auditManager->ecosystem($repository)
                ?? 'unknown';

            if (!$json) {
                $output->clearLine();

                $output->write(sprintf(
                    'Auditing [%d/%d] %s (%s)...',
                    $position,
                    $total,
                    $repository->name,
                    $ecosystem,
                ));
            }

            $result = $this->auditManager->audit($repository);

            $results[] = $result;

            if (!$json) {
                $output->clearLine();

                if (!$result->successful) {
                    $status = $output->errorText('failed');
                } elseif ($result->hasVulnerabilities()) {
                    $status = $output->warningText(sprintf(
                        '%d findings',
                        $result->vulnerabilityCount(),
                    ));
                } else {
                    $status = $output->successText('clean');
                }

                $output->line(sprintf(
                    '[%d/%d] %s (%s): %s',
                    $position,
                    $total,
                    $repository->name,
                    $ecosystem,
                    $status,
                ));
            }
        }

        if (!$json) {
            $output->line();
        }

        if (!$json) {
            $output->clearLine();

            $output->success(sprintf(
                'Audited %d repositories.',
                $total,
            ));

            $output->line();
        }

        if ($json) {
            $this->renderJson(
                results: $results,
                output: $output,
            );
        } else {
            $this->renderSummary(
                results: $results,
                output: $output,
            );

            $this->renderFindings(
                results: $results,
                output: $output,
            );
        }

        return $this->exitCode($results);
    }

    /**
     * @param list<Repository> $repositories
     */
    private function renderPreview(
        array  $repositories,
        Output $output,
    ): void
    {
        $rows = array_map(
            fn(Repository $repository): array => [
                $repository->name,
                $this->auditManager->ecosystem($repository) ?? 'unknown',
                $repository->path,
            ],
            $repositories,
        );

        $output->table(
            headers: [
                'REPOSITORY',
                'ECOSYSTEM',
                'PATH',
            ],
            rows: $rows,
            numbered: true,
            title: 'Repositories to Audit',
            footer: sprintf(
                '%d repositories selected.',
                count($repositories),
            ),
        );
    }


    /**
     * @param list<AuditResult> $results
     */
    private function renderJson(
        array  $results,
        Output $output,
    ): void
    {
        $repositories = array_map(
            static fn(AuditResult $result): array => [
                'repository' => [
                    'name' => $result->repository->name,
                    'path' => $result->repository->path,
                ],
                'ecosystem' => $result->ecosystem,
                'successful' => $result->successful,
                'error' => $result->error,
                'findings' => array_map(
                    static fn(AuditFinding $finding): array => [
                        'package' => $finding->package,
                        'severity' => $finding->severity,
                        'installedVersion' => $finding->installedVersion,
                        'affectedRange' => $finding->affectedRange,
                        'patchedRange' => $finding->patchedRange,
                        'title' => $finding->title,
                        'advisoryId' => $finding->advisoryId,
                        'url' => $finding->url,
                        'recommendation' => $finding->recommendation,
                        'dependencyPaths' => $finding->dependencyPaths,
                        'cves' => $finding->cves,
                        'cvssScore' => $finding->cvssScore,
                        'direct' => $finding->direct,
                        'dependencyType' => $finding->dependencyType,
                        'reference' => $finding->reference(),
                    ],
                    $result->findings,
                ),
            ],
            $results,
        );

        $failed = count(array_filter(
            $results,
            static fn(AuditResult $result): bool => !$result->successful,
        ));

        $vulnerable = count(array_filter(
            $results,
            static fn(AuditResult $result): bool => $result->successful
                && $result->hasVulnerabilities(),
        ));

        $clean = count(array_filter(
            $results,
            static fn(AuditResult $result): bool => $result->successful
                && !$result->hasVulnerabilities(),
        ));

        $findings = array_sum(array_map(
            static fn(AuditResult $result): int => $result->vulnerabilityCount(),
            $results,
        ));

        $output->line($this->jsonRenderer->render([
            'repositories' => $repositories,
            'summary' => [
                'audited' => count($results),
                'clean' => $clean,
                'vulnerable' => $vulnerable,
                'failed' => $failed,
                'findings' => $findings,
            ],
        ]));
    }

    /**
     * @param list<AuditResult> $results
     */
    private function renderSummary(
        array  $results,
        Output $output,
    ): void
    {
        $rows = array_map(
            fn(AuditResult $result): array => [
                $result->repository->name,
                $result->ecosystem,
                $this->formatStatus($result, $output),
                (string)$result->vulnerabilityCount(),
                $result->error ?? '—',
            ],
            $results,
        );

        $output->table(
            headers: [
                'REPOSITORY',
                'ECOSYSTEM',
                'STATUS',
                'FINDINGS',
                'ERROR',
            ],
            rows: $rows,
            numbered: true,
            title: 'Dependency Audit Summary',
            footer: $this->summaryFooter($results),
        );
    }

    private function formatStatus(
        AuditResult $result,
        Output      $output,
    ): string
    {
        if (!$result->successful) {
            return $output->errorText('failed');
        }

        if ($result->hasVulnerabilities()) {
            return $output->warningText('vulnerable');
        }

        return $output->successText('clean');
    }

    /**
     * @param list<AuditResult> $results
     */
    private function summaryFooter(array $results): string
    {
        $failed = count(array_filter(
            $results,
            static fn(AuditResult $result): bool => !$result->successful,
        ));

        $vulnerable = count(array_filter(
            $results,
            static fn(AuditResult $result): bool => $result->successful
                && $result->hasVulnerabilities(),
        ));

        $clean = count(array_filter(
            $results,
            static fn(AuditResult $result): bool => $result->successful
                && !$result->hasVulnerabilities(),
        ));

        $findings = array_sum(array_map(
            static fn(AuditResult $result): int => $result->vulnerabilityCount(),
            $results,
        ));

        return sprintf(
            '%d audited, %d clean, %d vulnerable, %d failed, %d findings.',
            count($results),
            $clean,
            $vulnerable,
            $failed,
            $findings,
        );
    }

    /**
     * @param list<AuditResult> $results
     */
    private function renderFindings(
        array  $results,
        Output $output,
    ): void
    {
        $rows = [];

        foreach ($results as $result) {
            foreach ($result->findings as $finding) {
                $rows[] = [
                    $result->repository->name,
                    $finding->package,
                    $this->formatSeverity(
                        severity: $finding->severity,
                        output: $output,
                    ),
                    $finding->dependencyType,
                    $finding->affectedRange,
                    $finding->recommendation
                    ?? $finding->patchedRange
                        ?? '—',
                    $finding->reference(),
                ];
            }
        }

        if ($rows === []) {
            $output->success(
                'No known vulnerabilities were found.',
            );

            return;
        }

        $output->table(
            headers: [
                'REPOSITORY',
                'PACKAGE',
                'SEVERITY',
                'TYPE',
                'AFFECTED',
                'FIX',
                'REFERENCE',
            ],
            rows: $rows,
            numbered: true,
            title: 'Known Vulnerabilities',
            footer: sprintf(
                '%d vulnerability findings.',
                count($rows),
            ),
        );
    }

    private function formatSeverity(
        string $severity,
        Output $output,
    ): string
    {
        return match (strtolower($severity)) {
            'critical' => $output->errorText('critical'),
            'high' => $output->errorText('high'),
            'moderate', 'medium' => $output->warningText('moderate'),
            'low' => $output->mutedText('low'),
            default => $output->mutedText($severity),
        };
    }

    /**
     * @param list<AuditResult> $results
     */
    private function exitCode(array $results): int
    {
        foreach ($results as $result) {
            if (!$result->successful) {
                return 2;
            }
        }

        foreach ($results as $result) {
            if ($result->hasVulnerabilities()) {
                return 1;
            }
        }

        return 0;
    }

    protected function configure(): CommandDefinition
    {
        return new CommandDefinition(
            name: 'repos:audit',
            description: 'Audit project dependencies for known security vulnerabilities.',
            arguments: [
                new ArgumentDefinition(
                    name: 'path',
                    description: 'Project directory to audit.',
                    default: '.',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'json',
                    description: 'Output audit results as JSON.',
                ),
            ],
            examples: [
                'pine audit',
                'pine audit ~/Projects/MyApp',
                'pine audit --json',
            ],
        );
    }
}
