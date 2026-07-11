<?php

declare(strict_types=1);

namespace Pine\Audit;

use JsonException;
use Pine\Process\ProcessRunnerInterface;
use Pine\Repositories\Repository;

final readonly class PnpmAuditor implements DependencyAuditor
{
    public function __construct(
        private ProcessRunnerInterface $processRunner,
    )
    {
    }

    public function ecosystem(): string
    {
        return 'pnpm';
    }

    public function supports(Repository $repository): bool
    {
        return is_file(
            $repository->path
            . DIRECTORY_SEPARATOR
            . 'pnpm-lock.yaml',
        );
    }

    public function audit(Repository $repository): AuditResult
    {
        if (!$this->toolAvailable('pnpm')) {
            return $this->failure(
                repository: $repository,
                error: 'pnpm is not installed.',
            );
        }

        $result = $this->processRunner->run([
            'pnpm',
            '--dir',
            $repository->path,
            'audit',
            '--json',
        ]);

        if (trim($result->output) === '') {
            return new AuditResult(
                repository: $repository,
                ecosystem: 'pnpm',
                successful: false,
                error: 'pnpm audit returned no output.',
            );
        }

        try {
            $data = json_decode(
                $result->output,
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            return new AuditResult(
                repository: $repository,
                ecosystem: 'pnpm',
                successful: false,
                error: sprintf(
                    'Unable to parse pnpm audit output: %s',
                    $exception->getMessage(),
                ),
            );
        }

        if (!is_array($data)) {
            return new AuditResult(
                repository: $repository,
                ecosystem: 'pnpm',
                successful: false,
                error: 'pnpm audit returned an unexpected response.',
            );
        }

        if (isset($data['error'])) {
            return new AuditResult(
                repository: $repository,
                ecosystem: 'pnpm',
                successful: false,
                error: $this->extractError($data['error']),
            );
        }

        $dependencyTypes = $this->loadDependencyTypes(
            $repository->path,
        );

        return new AuditResult(
            repository: $repository,
            ecosystem: 'pnpm',
            successful: true,
            findings: $this->parseFindings(
                data: $data,
                dependencyTypes: $dependencyTypes,
            ),
        );
    }

    private function toolAvailable(string $command): bool
    {
        $result = $this->processRunner->run([
            'sh',
            '-c',
            sprintf(
                'command -v %s >/dev/null 2>&1',
                escapeshellarg($command),
            ),
        ]);

        return $result->successful();
    }

    private function extractError(mixed $error): string
    {
        if (is_string($error)) {
            return $error;
        }

        if (is_array($error)) {
            foreach (
                ['summary', 'detail', 'message', 'code']
                as $key
            ) {
                $value = $error[$key] ?? null;

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return 'pnpm audit failed.';
    }

    /**
     * @return array<string, string>
     */
    private function loadDependencyTypes(
        string $repositoryPath,
    ): array
    {
        $packageJsonPath = $repositoryPath
            . DIRECTORY_SEPARATOR
            . 'package.json';

        $contents = file_get_contents($packageJsonPath);

        if ($contents === false) {
            return [];
        }

        try {
            $packageJson = json_decode(
                $contents,
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return [];
        }

        if (!is_array($packageJson)) {
            return [];
        }

        $types = [];

        foreach (
            $packageJson['dependencies'] ?? []
            as $package => $_version
        ) {
            if (is_string($package)) {
                $types[$package] = 'dependency';
            }
        }

        foreach (
            $packageJson['devDependencies'] ?? []
            as $package => $_version
        ) {
            if (is_string($package)) {
                $types[$package] = 'devDependency';
            }
        }

        foreach (
            $packageJson['optionalDependencies'] ?? []
            as $package => $_version
        ) {
            if (is_string($package)) {
                $types[$package] = 'optionalDependency';
            }
        }

        return $types;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $dependencyTypes
     *
     * @return list<AuditFinding>
     */
    private function parseFindings(
        array $data,
        array $dependencyTypes,
    ): array
    {
        $advisories = $data['advisories'] ?? [];

        if (!is_array($advisories)) {
            return [];
        }

        $findings = [];

        foreach ($advisories as $advisory) {
            if (!is_array($advisory)) {
                continue;
            }

            $package = $advisory['module_name'] ?? null;

            if (!is_string($package) || $package === '') {
                continue;
            }

            /*
             * Only report packages directly declared in package.json.
             * This includes dependencies, devDependencies and
             * optionalDependencies.
             */
            $dependencyType = $dependencyTypes[$package] ?? null;

            if ($dependencyType === null) {
                continue;
            }

            $installedFindings = $advisory['findings'] ?? [];

            if (!is_array($installedFindings)) {
                continue;
            }

            foreach ($installedFindings as $installedFinding) {
                if (!is_array($installedFinding)) {
                    continue;
                }

                $findings[] = new AuditFinding(
                    package: $package,
                    severity: $this->stringValue(
                        $advisory['severity'] ?? null,
                        'unknown',
                    ),
                    installedVersion: $this->nullableString(
                        $installedFinding['version'] ?? null,
                    ),
                    affectedRange: $this->stringValue(
                        $advisory['vulnerable_versions'] ?? null,
                        'unknown',
                    ),
                    patchedRange: $this->nullableString(
                        $advisory['patched_versions'] ?? null,
                    ),
                    title: $this->stringValue(
                        $advisory['title'] ?? null,
                        'Known vulnerability',
                    ),
                    advisoryId: $this->extractAdvisoryId(
                        $advisory,
                    ),
                    url: $this->nullableString(
                        $advisory['url'] ?? null,
                    ),
                    recommendation: $this->nullableString(
                        $advisory['recommendation'] ?? null,
                    ),
                    dependencyType: $dependencyType,
                    dependencyPaths: $this->stringList(
                        $installedFinding['paths'] ?? [],
                    ),
                    cves: $this->stringList(
                        $advisory['cves'] ?? [],
                    ),
                    cvssScore: $this->extractCvssScore(
                        $advisory,
                    ),
                    direct: true,
                );
            }
        }

        return $findings;
    }

    private function stringValue(
        mixed  $value,
        string $default,
    ): string
    {
        return is_string($value) && $value !== ''
            ? $value
            : $default;
    }

    private function nullableString(
        mixed $value,
    ): ?string
    {
        return is_string($value) && $value !== ''
            ? $value
            : null;
    }

    /**
     * @param array<string, mixed> $advisory
     */
    private function extractAdvisoryId(
        array $advisory,
    ): string
    {
        $githubAdvisoryId =
            $advisory['github_advisory_id'] ?? null;

        if (
            is_string($githubAdvisoryId)
            && $githubAdvisoryId !== ''
        ) {
            return $githubAdvisoryId;
        }

        $id = $advisory['id'] ?? null;

        if (is_int($id) || is_string($id)) {
            return (string)$id;
        }

        $url = $advisory['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $path = parse_url($url, PHP_URL_PATH);

            if (is_string($path)) {
                $identifier = basename($path);

                if ($identifier !== '') {
                    return $identifier;
                }
            }
        }

        return 'unknown';
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn(mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    /**
     * @param array<string, mixed> $advisory
     */
    private function extractCvssScore(
        array $advisory,
    ): ?float
    {
        $cvss = $advisory['cvss'] ?? null;

        if (!is_array($cvss)) {
            return null;
        }

        $score = $cvss['score'] ?? null;

        return is_int($score) || is_float($score)
            ? (float)$score
            : null;
    }
}
