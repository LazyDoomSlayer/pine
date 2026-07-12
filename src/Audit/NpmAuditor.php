<?php

declare(strict_types=1);

namespace Pine\Audit;

use JsonException;
use Pine\Process\ProcessRunnerInterface;
use Pine\Repositories\Repository;

final readonly class NpmAuditor implements DependencyAuditor
{
    public function __construct(
        private ProcessRunnerInterface $processRunner,
    )
    {
    }

    public function supports(Repository $repository): bool
    {

        return is_file(
                $repository->path
                . DIRECTORY_SEPARATOR
                . 'package-lock.json',
            ) || is_file(
                $repository->path
                . DIRECTORY_SEPARATOR
                . 'npm-shrinkwrap.json',
            );

    }

    public function audit(Repository $repository): AuditResult
    {
        if (!$this->toolAvailable('npm')) {
            return $this->failure(
                repository: $repository,
                error: 'npm is not installed.',
            );
        }

        $result = $this->processRunner->run([
            'npm',
            'audit',
            '--json',
            '--prefix',
            $repository->path,
        ]);

        if (trim($result->output) === '') {
            return new AuditResult(
                repository: $repository,
                ecosystem: 'npm',
                successful: false,
                error: 'npm audit returned no output.',
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
                ecosystem: 'npm',
                successful: false,
                error: sprintf(
                    'Unable to parse npm audit output: %s',
                    $exception->getMessage(),
                ),
            );
        }

        if (!is_array($data)) {
            return new AuditResult(
                repository: $repository,
                ecosystem: 'npm',
                successful: false,
                error: 'npm audit returned an unexpected response.',
            );
        }

        if (isset($data['error'])) {
            return new AuditResult(
                repository: $repository,
                ecosystem: 'npm',
                successful: false,
                error: $this->extractError($data['error']),
            );
        }

        $dependencyTypes = $this->loadDependencyTypes(
            $repository->path,
        );

        return new AuditResult(
            repository: $repository,
            ecosystem: 'npm',
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

    private function failure(
        Repository $repository,
        string     $error,
    ): AuditResult
    {
        return new AuditResult(
            repository: $repository,
            ecosystem: $this->ecosystem(),
            successful: false,
            error: $error,
        );
    }

    public function ecosystem(): string
    {
        return 'npm';
    }

    private function extractError(mixed $error): string
    {
        if (is_string($error)) {
            return $error;
        }

        if (is_array($error)) {
            foreach (['summary', 'detail', 'message', 'code'] as $key) {
                $value = $error[$key] ?? null;

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return 'npm audit failed.';
    }

    /**
     * @return array<string, string>
     */
    private function loadDependencyTypes(string $repositoryPath): array
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

        foreach ($packageJson['dependencies'] ?? [] as $package => $_version) {
            if (is_string($package)) {
                $types[$package] = 'dependency';
            }
        }

        foreach ($packageJson['devDependencies'] ?? [] as $package => $_version) {
            if (is_string($package)) {
                $types[$package] = 'devDependency';
            }
        }

        foreach ($packageJson['optionalDependencies'] ?? [] as $package => $_version) {
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
        $vulnerabilities = $data['vulnerabilities'] ?? [];

        if (!is_array($vulnerabilities)) {
            return [];
        }

        $findings = [];

        foreach ($vulnerabilities as $package => $vulnerability) {
            if (!is_string($package) || !is_array($vulnerability)) {
                continue;
            }

            if (($vulnerability['isDirect'] ?? false) !== true) {
                continue;
            }

            $via = $vulnerability['via'] ?? [];

            if (!is_array($via)) {
                continue;
            }

            foreach ($via as $advisory) {
                if (!is_array($advisory)) {
                    continue;
                }

                $findings[] = new AuditFinding(
                    package: $package,
                    severity: $this->stringValue(
                        $advisory['severity'] ?? null,
                        $this->stringValue(
                            $vulnerability['severity'] ?? null,
                            'unknown',
                        ),
                    ),
                    installedVersion: null,
                    affectedRange: $this->stringValue(
                        $advisory['range'] ?? null,
                        $this->stringValue(
                            $vulnerability['range'] ?? null,
                            'unknown',
                        ),
                    ),
                    patchedRange: null,
                    title: $this->stringValue(
                        $advisory['title'] ?? null,
                        'Known vulnerability',
                    ),
                    advisoryId: $this->extractAdvisoryId($advisory),
                    url: $this->nullableString($advisory['url'] ?? null),
                    recommendation: $this->extractRecommendation($vulnerability),
                    dependencyPaths: $this->stringList(
                        $vulnerability['nodes'] ?? [],
                    ),
                    cves: [],
                    cvssScore: $this->extractCvssScore($advisory),
                    direct: ($vulnerability['isDirect'] ?? false) === true,
                    dependencyType: $dependencyTypes[$package] ?? 'unknown',
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

    /**
     * @param array<string, mixed> $advisory
     */
    private function extractAdvisoryId(array $advisory): string
    {
        $source = $advisory['source'] ?? null;

        if (is_int($source) || is_string($source)) {
            return (string)$source;
        }

        $url = $advisory['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $path = parse_url($url, PHP_URL_PATH);

            if (is_string($path)) {
                $id = basename($path);

                if ($id !== '') {
                    return $id;
                }
            }
        }

        return 'unknown';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== ''
            ? $value
            : null;
    }

    /**
     * @param array<string, mixed> $vulnerability
     */
    private function extractRecommendation(array $vulnerability): ?string
    {
        $fixAvailable = $vulnerability['fixAvailable'] ?? null;

        if ($fixAvailable === true) {
            return 'A fix is available.';
        }

        if (!is_array($fixAvailable)) {
            return null;
        }

        $name = $fixAvailable['name'] ?? null;
        $version = $fixAvailable['version'] ?? null;

        if (is_string($name) && is_string($version)) {
            return sprintf(
                '%s%s',
                $version,
                ($fixAvailable['isSemVerMajor'] ?? false)
                    ? ' (major)'
                    : '',
            );
        }

        return 'A fix is available.';
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(
            array_filter(
                $value,
                static fn(mixed $item): bool => is_string($item),
            ),
        );
    }

    /**
     * @param array<string, mixed> $advisory
     */
    private function extractCvssScore(array $advisory): ?float
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
