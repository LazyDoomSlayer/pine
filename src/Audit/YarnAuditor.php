<?php

declare(strict_types=1);

namespace Pine\Audit;

use JsonException;
use Pine\Process\ProcessRunnerInterface;
use Pine\Repositories\Repository;

final readonly class YarnAuditor implements DependencyAuditor
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
            . 'yarn.lock',
        );
    }

    public function audit(Repository $repository): AuditResult
    {
        if (!$this->toolAvailable('yarn')) {
            return $this->failure(
                repository: $repository,
                error: 'yarn is not installed.',
            );
        }
        
        $version = $this->detectVersion($repository);

        return match ($version) {
            YarnVersion::Modern => $this->auditModern($repository),
            YarnVersion::Classic => $this->auditClassic($repository),
        };
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
        return 'yarn';
    }

    private function detectVersion(
        Repository $repository,
    ): YarnVersion
    {
        if (is_file(
            $repository->path
            . DIRECTORY_SEPARATOR
            . '.yarnrc.yml',
        )) {
            return YarnVersion::Modern;
        }

        $packageManager = $this->readPackageManager(
            $repository->path,
        );

        if (
            $packageManager !== null
            && str_starts_with($packageManager, 'yarn@')
        ) {
            $version = substr($packageManager, strlen('yarn@'));

            if (
                $version !== ''
                && version_compare($version, '2.0.0', '>=')
            ) {
                return YarnVersion::Modern;
            }
        }

        return YarnVersion::Classic;
    }

    private function readPackageManager(
        string $repositoryPath,
    ): ?string
    {
        $path = $repositoryPath
            . DIRECTORY_SEPARATOR
            . 'package.json';

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        try {
            $data = json_decode(
                $contents,
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return null;
        }

        $packageManager = $data['packageManager'] ?? null;

        return is_string($packageManager)
            ? $packageManager
            : null;
    }

    private function auditModern(
        Repository $repository,
    ): AuditResult
    {
        $result = $this->processRunner->run([
            'yarn',
            '--cwd',
            $repository->path,
            'npm',
            'audit',
            '--json',
        ]);

        if (trim($result->output) === '') {
            return $this->failure(
                repository: $repository,
                error: 'Yarn audit returned no output.',
            );
        }

        try {
            $data = json_decode(
                $result->output,
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            return $this->failure(
                repository: $repository,
                error: sprintf(
                    'Unable to parse Yarn audit output: %s',
                    $exception->getMessage(),
                ),
            );
        }

        if (!is_array($data)) {
            return $this->failure(
                repository: $repository,
                error: 'Yarn audit returned an unexpected response.',
            );
        }

        return new AuditResult(
            repository: $repository,
            ecosystem: $this->ecosystem(),
            successful: true,
            findings: $this->parseModernFindings(
                data: $data,
                dependencyTypes: $this->loadDependencyTypes(
                    $repository->path,
                ),
            ),
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $dependencyTypes
     *
     * @return list<AuditFinding>
     */
    private function parseModernFindings(
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

            $package = $advisory['module_name']
                ?? $advisory['name']
                ?? null;

            if (!is_string($package) || $package === '') {
                continue;
            }

            $dependencyType = $dependencyTypes[$package] ?? null;

            if ($dependencyType === null) {
                continue;
            }

            $findings[] = new AuditFinding(
                package: $package,
                severity: $this->stringValue(
                    $advisory['severity'] ?? null,
                    'unknown',
                ),
                installedVersion: null,
                affectedRange: $this->stringValue(
                    $advisory['vulnerable_versions']
                    ?? $advisory['range']
                    ?? null,
                    'unknown',
                ),
                patchedRange: $this->nullableString(
                    $advisory['patched_versions'] ?? null,
                ),
                title: $this->stringValue(
                    $advisory['title'] ?? null,
                    'Known vulnerability',
                ),
                advisoryId: $this->extractAdvisoryId($advisory),
                url: $this->nullableString(
                    $advisory['url'] ?? null,
                ),
                recommendation: $this->nullableString(
                    $advisory['recommendation'] ?? null,
                ),
                dependencyType: $dependencyType,
                dependencyPaths: [],
                cves: $this->stringList(
                    $advisory['cves'] ?? [],
                ),
                cvssScore: $this->extractCvssScore($advisory),
                direct: true,
            );
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
    private function extractAdvisoryId(array $advisory): string
    {
        foreach (
            ['github_advisory_id', 'id', 'source']
            as $key
        ) {
            $value = $advisory[$key] ?? null;

            if (is_int($value) || is_string($value)) {
                return (string)$value;
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

    /**
     * @return array<string, string>
     */
    private function loadDependencyTypes(
        string $repositoryPath,
    ): array
    {
        $path = $repositoryPath
            . DIRECTORY_SEPARATOR
            . 'package.json';

        $contents = file_get_contents($path);

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

        foreach ($packageJson['dependencies'] ?? [] as $package => $_) {
            if (is_string($package)) {
                $types[$package] = 'dependency';
            }
        }

        foreach ($packageJson['devDependencies'] ?? [] as $package => $_) {
            if (is_string($package)) {
                $types[$package] = 'devDependency';
            }
        }

        foreach ($packageJson['optionalDependencies'] ?? [] as $package => $_) {
            if (is_string($package)) {
                $types[$package] = 'optionalDependency';
            }
        }

        return $types;
    }

    private function auditClassic(
        Repository $repository,
    ): AuditResult
    {
        $result = $this->processRunner->run([
            'yarn',
            '--cwd',
            $repository->path,
            'audit',
            '--json',
        ]);

        if (trim($result->output) === '') {
            return $this->failure(
                repository: $repository,
                error: 'Yarn Classic audit returned no output.',
            );
        }

        $dependencyTypes = $this->loadDependencyTypes(
            $repository->path,
        );

        $findings = [];

        foreach (preg_split('/\R/', trim($result->output)) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            try {
                $event = json_decode(
                    $line,
                    true,
                    flags: JSON_THROW_ON_ERROR,
                );
            } catch (JsonException) {
                continue;
            }

            if (!is_array($event)) {
                continue;
            }

            if (($event['type'] ?? null) !== 'auditAdvisory') {
                continue;
            }

            $finding = $this->parseClassicFinding(
                event: $event,
                dependencyTypes: $dependencyTypes,
            );

            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        return new AuditResult(
            repository: $repository,
            ecosystem: $this->ecosystem(),
            successful: true,
            findings: $findings,
        );
    }

    /**
     * @param array<string, mixed> $event
     * @param array<string, string> $dependencyTypes
     */
    private function parseClassicFinding(
        array $event,
        array $dependencyTypes,
    ): ?AuditFinding
    {
        $data = $event['data'] ?? null;

        if (!is_array($data)) {
            return null;
        }

        $advisory = $data['advisory'] ?? null;

        if (!is_array($advisory)) {
            return null;
        }

        $package = $advisory['module_name'] ?? null;

        if (!is_string($package) || $package === '') {
            return null;
        }

        $dependencyType = $dependencyTypes[$package] ?? null;

        if ($dependencyType === null) {
            return null;
        }

        $resolution = $data['resolution'] ?? [];
        $installedVersion = is_array($resolution)
            ? $this->nullableString($resolution['id'] ?? null)
            : null;

        return new AuditFinding(
            package: $package,
            severity: $this->stringValue(
                $advisory['severity'] ?? null,
                'unknown',
            ),
            installedVersion: $installedVersion,
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
            advisoryId: $this->extractAdvisoryId($advisory),
            url: $this->nullableString(
                $advisory['url'] ?? null,
            ),
            recommendation: $this->nullableString(
                $advisory['recommendation'] ?? null,
            ),
            dependencyType: $dependencyType,
            dependencyPaths: [],
            cves: $this->stringList(
                $advisory['cves'] ?? [],
            ),
            cvssScore: $this->extractCvssScore($advisory),
            direct: true,
        );
    }

}
