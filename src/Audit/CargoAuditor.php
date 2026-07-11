<?php

declare(strict_types=1);

namespace Pine\Audit;

use JsonException;
use Pine\Process\ProcessRunnerInterface;
use Pine\Repositories\Repository;

final readonly class CargoAuditor implements DependencyAuditor
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
            . 'Cargo.lock',
        );
    }

    public function audit(Repository $repository): AuditResult
    {
        $result = $this->processRunner->run([
            'cargo',
            'audit',
            '--version',
        ]);

        if (!$result->successful()) {
            return $this->failure(
                repository: $repository,
                error: 'cargo-audit is not installed.',
            );
        }

        $result = $this->processRunner->run([
            'cargo',
            'audit',
            '--json',
            '--file',
            $repository->path
            . DIRECTORY_SEPARATOR
            . 'Cargo.lock',
        ]);

        if (trim($result->output) === '') {
            return $this->failure(
                repository: $repository,
                error: 'cargo audit returned no output. Make sure cargo-audit is installed.',
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
                    'Unable to parse cargo audit output: %s',
                    $exception->getMessage(),
                ),
            );
        }

        if (!is_array($data)) {
            return $this->failure(
                repository: $repository,
                error: 'cargo audit returned an unexpected response.',
            );
        }

        return new AuditResult(
            repository: $repository,
            ecosystem: $this->ecosystem(),
            successful: true,
            findings: $this->parseFindings($data),
        );
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
        return 'cargo';
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<AuditFinding>
     */
    private function parseFindings(array $data): array
    {
        $vulnerabilities = $data['vulnerabilities'] ?? null;

        if (!is_array($vulnerabilities)) {
            return [];
        }

        $entries = $vulnerabilities['list'] ?? [];

        if (!is_array($entries)) {
            return [];
        }

        $findings = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $advisory = $entry['advisory'] ?? null;
            $package = $entry['package'] ?? null;
            $versions = $entry['versions'] ?? null;

            if (!is_array($advisory) || !is_array($package)) {
                continue;
            }

            $packageName = $package['name'] ?? null;

            if (!is_string($packageName) || $packageName === '') {
                continue;
            }

            $findings[] = new AuditFinding(
                package: $packageName,
                severity: $this->extractSeverity($advisory),
                installedVersion: $this->nullableString(
                    $package['version'] ?? null,
                ),
                affectedRange: $this->extractAffectedRange($versions),
                patchedRange: $this->extractPatchedRange($versions),
                title: $this->stringValue(
                    $advisory['title'] ?? null,
                    'Known RustSec vulnerability',
                ),
                advisoryId: $this->stringValue(
                    $advisory['id'] ?? null,
                    'unknown',
                ),
                url: $this->nullableString(
                    $advisory['url'] ?? null,
                ),
                recommendation: $this->extractRecommendation($versions),
                dependencyType: 'dependency',
                dependencyPaths: [],
                cves: $this->extractCves($advisory),
                cvssScore: $this->extractCvssScore($advisory),
                direct: false,
            );
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $advisory
     */
    private function extractSeverity(array $advisory): string
    {
        $cvss = $this->extractCvssScore($advisory);

        if ($cvss === null) {
            return 'unknown';
        }

        return match (true) {
            $cvss >= 9.0 => 'critical',
            $cvss >= 7.0 => 'high',
            $cvss >= 4.0 => 'moderate',
            $cvss > 0.0 => 'low',
            default => 'unknown',
        };
    }

    /**
     * @param array<string, mixed> $advisory
     */
    private function extractCvssScore(array $advisory): ?float
    {
        $cvss = $advisory['cvss'] ?? null;

        if (is_int($cvss) || is_float($cvss)) {
            return (float)$cvss;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== ''
            ? $value
            : null;
    }

    private function extractAffectedRange(mixed $versions): string
    {
        if (!is_array($versions)) {
            return 'unknown';
        }

        $patched = $this->stringList(
            $versions['patched'] ?? [],
        );

        $unaffected = $this->stringList(
            $versions['unaffected'] ?? [],
        );

        $patchedVersion = $this->extractBoundaryVersion(
            $patched[0] ?? null,
        );

        $unaffectedVersion = $this->extractBoundaryVersion(
            $unaffected[0] ?? null,
        );

        if (
            $patchedVersion !== null
            && $unaffectedVersion !== null
        ) {
            return sprintf(
                '>=%s <%s',
                $unaffectedVersion,
                $patchedVersion,
            );
        }

        if ($patchedVersion !== null) {
            return sprintf(
                '<%s',
                $patchedVersion,
            );
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

    private function extractBoundaryVersion(?string $constraint): ?string
    {
        if ($constraint === null || $constraint === '') {
            return null;
        }

        $version = preg_replace(
            '/^[<>=~^\s]+/',
            '',
            $constraint,
        );

        if (!is_string($version) || $version === '') {
            return null;
        }

        return $version;
    }

    private function extractPatchedRange(mixed $versions): ?string
    {
        if (!is_array($versions)) {
            return null;
        }

        $patched = $this->stringList(
            $versions['patched'] ?? [],
        );

        return $patched === []
            ? null
            : implode(', ', $patched);
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

    private function extractRecommendation(mixed $versions): ?string
    {
        $patchedRange = $this->extractPatchedRange($versions);

        if ($patchedRange === null) {
            return null;
        }

        return sprintf(
            'Upgrade to %s',
            $patchedRange,
        );
    }

    /**
     * @param array<string, mixed> $advisory
     *
     * @return list<string>
     */
    private function extractCves(array $advisory): array
    {
        $aliases = $this->stringList(
            $advisory['aliases'] ?? [],
        );

        return array_values(array_filter(
            $aliases,
            static fn(string $alias): bool => str_starts_with($alias, 'CVE-'),
        ));
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

}
