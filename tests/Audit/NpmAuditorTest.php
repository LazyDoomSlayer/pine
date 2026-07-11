<?php

declare(strict_types=1);

namespace Pine\Tests\Audit;

use PHPUnit\Framework\TestCase;
use Pine\Audit\NpmAuditor;
use Pine\Process\ProcessResult;
use Pine\Repositories\Repository;
use Pine\Tests\Support\FakeProcessRunner;
use RuntimeException;

final class NpmAuditorTest extends TestCase
{
    private string $temporaryDirectory;

    public function testItParsesOptionalDependencyVulnerability(): void
    {
        $this->createNpmProject([
            'optionalDependencies' => [
                'optional-package' => '^1.0.0',
            ],
        ]);

        $runner = new FakeProcessRunner(
            result: new ProcessResult(
                exitCode: 1,
                output: json_encode(
                    [
                        'auditReportVersion' => 2,
                        'vulnerabilities' => [
                            'optional-package' => [
                                'name' => 'optional-package',
                                'severity' => 'high',
                                'isDirect' => true,
                                'via' => [
                                    [
                                        'source' => 1234,
                                        'title' => 'Optional package vulnerability',
                                        'url' => 'https://github.com/advisories/GHSA-optional',
                                        'severity' => 'high',
                                        'range' => '<2.0.0',
                                    ],
                                ],
                                'range' => '<2.0.0',
                                'nodes' => [
                                    'node_modules/optional-package',
                                ],
                                'fixAvailable' => true,
                            ],
                        ],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        $result = (new NpmAuditor($runner))
            ->audit($this->repository());

        self::assertSame(1, $result->vulnerabilityCount());

        self::assertSame(
            'optionalDependency',
            $result->findings[0]->dependencyType,
        );
    }

    /**
     * @param array<string, mixed> $packageJson
     */
    private function createNpmProject(array $packageJson): void
    {
        file_put_contents(
            $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . 'package.json',
            json_encode(
                $packageJson,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT,
            ),
        );

        file_put_contents(
            $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . 'package-lock.json',
            '{}',
        );
    }

    private function repository(): Repository
    {
        return new Repository(
            name: 'npm-project',
            path: $this->temporaryDirectory,
        );
    }

    public function testItSupportsRepositoryWithPackageLock(): void
    {
        file_put_contents(
            $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . 'package-lock.json',
            '{}',
        );

        $auditor = new NpmAuditor(
            processRunner: $this->fakeRunner(),
        );

        $repository = new Repository(
            name: 'npm-project',
            path: $this->temporaryDirectory,
        );

        self::assertTrue(
            $auditor->supports($repository),
        );
    }

    private function fakeRunner(): FakeProcessRunner
    {
        return new FakeProcessRunner(
            result: new ProcessResult(
                exitCode: 0,
                output: '{}',
            ),
        );
    }

    public function testItSupportsRepositoryWithNpmShrinkwrap(): void
    {
        file_put_contents(
            $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . 'npm-shrinkwrap.json',
            '{}',
        );

        $auditor = new NpmAuditor(
            processRunner: $this->fakeRunner(),
        );

        $repository = new Repository(
            name: 'npm-project',
            path: $this->temporaryDirectory,
        );

        self::assertTrue(
            $auditor->supports($repository),
        );
    }

    public function testItDoesNotSupportRepositoryWithoutNpmLockfile(): void
    {
        file_put_contents(
            $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . 'package.json',
            '{}',
        );

        $auditor = new NpmAuditor(
            processRunner: $this->fakeRunner(),
        );

        $repository = new Repository(
            name: 'unlocked-npm-project',
            path: $this->temporaryDirectory,
        );

        self::assertFalse(
            $auditor->supports($repository),
        );
    }

    public function testItReturnsCleanResultWhenNpmReportsNoVulnerabilities(): void
    {
        $this->createNpmProject([
            'dependencies' => [
                'axios' => '^1.16.0',
            ],
        ]);

        $runner = new FakeProcessRunner(
            result: new ProcessResult(
                exitCode: 0,
                output: json_encode(
                    [
                        'auditReportVersion' => 2,
                        'vulnerabilities' => [],
                        'metadata' => [
                            'vulnerabilities' => [
                                'total' => 0,
                            ],
                        ],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        $auditor = new NpmAuditor(
            processRunner: $runner,
        );

        $result = $auditor->audit($this->repository());

        self::assertTrue($result->successful);
        self::assertFalse($result->hasVulnerabilities());
        self::assertSame(0, $result->vulnerabilityCount());
        self::assertNull($result->error);

        self::assertSame(
            [
                'npm',
                'audit',
                '--json',
                '--prefix',
                $this->temporaryDirectory,
            ],
            $runner->lastCommand,
        );
    }

    public function testItParsesDirectDependencyVulnerability(): void
    {
        $this->createNpmProject([
            'dependencies' => [
                '@nestjs/core' => '^10.0.0',
            ],
        ]);

        $runner = new FakeProcessRunner(
            result: new ProcessResult(
                exitCode: 1,
                output: json_encode(
                    [
                        'auditReportVersion' => 2,
                        'vulnerabilities' => [
                            '@nestjs/core' => [
                                'name' => '@nestjs/core',
                                'severity' => 'moderate',
                                'isDirect' => true,
                                'via' => [
                                    [
                                        'source' => 1117063,
                                        'name' => '@nestjs/core',
                                        'dependency' => '@nestjs/core',
                                        'title' => 'NestJS core injection vulnerability',
                                        'url' => 'https://github.com/advisories/GHSA-36xv-jgw5-4q75',
                                        'severity' => 'moderate',
                                        'cvss' => [
                                            'score' => 6.1,
                                            'vectorString' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:N/A:L',
                                        ],
                                        'range' => '<=11.1.17',
                                    ],
                                ],
                                'range' => '<=11.1.17',
                                'nodes' => [
                                    'node_modules/@nestjs/core',
                                ],
                                'fixAvailable' => [
                                    'name' => '@nestjs/core',
                                    'version' => '11.1.28',
                                    'isSemVerMajor' => true,
                                ],
                            ],
                        ],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        $auditor = new NpmAuditor(
            processRunner: $runner,
        );

        $result = $auditor->audit($this->repository());

        self::assertTrue($result->successful);
        self::assertTrue($result->hasVulnerabilities());
        self::assertSame(1, $result->vulnerabilityCount());

        $finding = $result->findings[0];

        self::assertSame('@nestjs/core', $finding->package);
        self::assertSame('moderate', $finding->severity);
        self::assertSame('<=11.1.17', $finding->affectedRange);
        self::assertSame('NestJS core injection vulnerability', $finding->title);
        self::assertSame('1117063', $finding->advisoryId);
        self::assertSame(
            'https://github.com/advisories/GHSA-36xv-jgw5-4q75',
            $finding->url,
        );
        self::assertSame('11.1.28 (major)', $finding->recommendation);
        self::assertSame('dependency', $finding->dependencyType);
        self::assertSame(6.1, $finding->cvssScore);
        self::assertTrue($finding->direct);

        self::assertSame('npm', $result->ecosystem);

        self::assertSame(
            $this->repository()->path,
            $result->repository->path,
        );

        self::assertSame(
            ['node_modules/@nestjs/core'],
            $finding->dependencyPaths,
        );

        self::assertSame([], $finding->cves);
        self::assertNull($finding->installedVersion);
        self::assertNull($finding->patchedRange);

        self::assertSame(
            'https://github.com/advisories/GHSA-36xv-jgw5-4q75',
            $finding->reference(),
        );
    }

    public function testItParsesDirectDevDependencyVulnerability(): void
    {
        $this->createNpmProject([
            'devDependencies' => [
                'vite' => '^7.0.0',
            ],
        ]);

        $runner = new FakeProcessRunner(
            result: new ProcessResult(
                exitCode: 1,
                output: json_encode(
                    [
                        'auditReportVersion' => 2,
                        'vulnerabilities' => [
                            'vite' => [
                                'name' => 'vite',
                                'severity' => 'high',
                                'isDirect' => true,
                                'via' => [
                                    [
                                        'source' => 123456,
                                        'title' => 'Vite development server vulnerability',
                                        'url' => 'https://github.com/advisories/GHSA-example',
                                        'severity' => 'high',
                                        'cvss' => [
                                            'score' => 7.5,
                                        ],
                                        'range' => '<7.3.5',
                                    ],
                                ],
                                'range' => '<7.3.5',
                                'nodes' => [
                                    'node_modules/vite',
                                ],
                                'fixAvailable' => true,
                            ],
                        ],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );


        $result = (new NpmAuditor($runner))
            ->audit($this->repository());

        self::assertSame(1, $result->vulnerabilityCount());
        self::assertSame(
            'devDependency',
            $result->findings[0]->dependencyType,
        );
        self::assertSame(
            'A fix is available.',
            $result->findings[0]->recommendation,
        );
    }

    public function testItUsesUnknownDependencyTypeWhenPackageJsonIsInvalid(): void
    {
        file_put_contents(
            $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . 'package.json',
            '{invalid json',
        );

        file_put_contents(
            $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . 'package-lock.json',
            '{}',
        );

        $runner = new FakeProcessRunner(
            result: new ProcessResult(
                exitCode: 1,
                output: json_encode(
                    [
                        'auditReportVersion' => 2,
                        'vulnerabilities' => [
                            'axios' => [
                                'name' => 'axios',
                                'severity' => 'high',
                                'isDirect' => true,
                                'via' => [
                                    [
                                        'source' => 1001,
                                        'title' => 'Axios vulnerability',
                                        'url' => 'https://github.com/advisories/GHSA-axios',
                                        'severity' => 'high',
                                        'range' => '<1.16.0',
                                    ],
                                ],
                                'range' => '<1.16.0',
                                'nodes' => [
                                    'node_modules/axios',
                                ],
                                'fixAvailable' => true,
                            ],
                        ],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        $result = (new NpmAuditor($runner))
            ->audit($this->repository());

        self::assertSame(1, $result->vulnerabilityCount());

        self::assertSame(
            'unknown',
            $result->findings[0]->dependencyType,
        );
    }

    public function testItIgnoresTransitiveVulnerabilities(): void
    {
        $this->createNpmProject([
            'dependencies' => [
                '@nestjs/core' => '^10.0.0',
            ],
        ]);

        $runner = new FakeProcessRunner(
            result: new ProcessResult(
                exitCode: 1,
                output: json_encode(
                    [
                        'auditReportVersion' => 2,
                        'vulnerabilities' => [
                            'multer' => [
                                'name' => 'multer',
                                'severity' => 'high',
                                'isDirect' => false,
                                'via' => [
                                    [
                                        'source' => 987654,
                                        'title' => 'Multer denial of service',
                                        'url' => 'https://github.com/advisories/GHSA-multer',
                                        'severity' => 'high',
                                        'range' => '<2.2.0',
                                    ],
                                ],
                                'range' => '<2.2.0',
                                'nodes' => [
                                    'node_modules/multer',
                                ],
                                'fixAvailable' => true,
                            ],
                        ],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        $result = (new NpmAuditor($runner))
            ->audit($this->repository());

        self::assertTrue($result->successful);
        self::assertSame([], $result->findings);
    }

    public function testItCreatesOneFindingForEachAdvisory(): void
    {
        $this->createNpmProject([
            'dependencies' => [
                'axios' => '^1.0.0',
            ],
        ]);

        $runner = new FakeProcessRunner(
            result: new ProcessResult(
                exitCode: 1,
                output: json_encode(
                    [
                        'auditReportVersion' => 2,
                        'vulnerabilities' => [
                            'axios' => [
                                'name' => 'axios',
                                'severity' => 'high',
                                'isDirect' => true,
                                'via' => [
                                    [
                                        'source' => 1001,
                                        'title' => 'Axios vulnerability one',
                                        'url' => 'https://github.com/advisories/GHSA-one',
                                        'severity' => 'high',
                                        'range' => '<1.16.0',
                                    ],
                                    [
                                        'source' => 1002,
                                        'title' => 'Axios vulnerability two',
                                        'url' => 'https://github.com/advisories/GHSA-two',
                                        'severity' => 'moderate',
                                        'range' => '<1.16.0',
                                    ],
                                ],
                                'range' => '<1.16.0',
                                'nodes' => [
                                    'node_modules/axios',
                                ],
                                'fixAvailable' => [
                                    'name' => 'axios',
                                    'version' => '1.16.0',
                                    'isSemVerMajor' => false,
                                ],
                            ],
                        ],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        $result = (new NpmAuditor($runner))
            ->audit($this->repository());

        self::assertSame(2, $result->vulnerabilityCount());
        self::assertSame('1001', $result->findings[0]->advisoryId);
        self::assertSame('1002', $result->findings[1]->advisoryId);
    }

    public function testItIgnoresStringViaEntries(): void
    {
        $this->createNpmProject([
            'dependencies' => [
                '@nestjs/core' => '^10.0.0',
            ],
        ]);

        $runner = new FakeProcessRunner(
            result: new ProcessResult(
                exitCode: 1,
                output: json_encode(
                    [
                        'auditReportVersion' => 2,
                        'vulnerabilities' => [
                            '@nestjs/core' => [
                                'name' => '@nestjs/core',
                                'severity' => 'moderate',
                                'isDirect' => true,
                                'via' => [
                                    'file-type',
                                    [
                                        'source' => 1117063,
                                        'title' => 'Direct advisory',
                                        'url' => 'https://github.com/advisories/GHSA-direct',
                                        'severity' => 'moderate',
                                        'range' => '<=11.1.17',
                                    ],
                                ],
                                'range' => '<=11.1.17',
                                'nodes' => [
                                    'node_modules/@nestjs/core',
                                ],
                                'fixAvailable' => true,
                            ],
                        ],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        $result = (new NpmAuditor($runner))
            ->audit($this->repository());

        self::assertSame(1, $result->vulnerabilityCount());
        self::assertSame(
            'Direct advisory',
            $result->findings[0]->title,
        );
    }

    public function testItReturnsFailureForInvalidJson(): void
    {
        $this->createNpmProject([]);

        $runner = new FakeProcessRunner(
            result: new ProcessResult(
                exitCode: 1,
                output: 'not valid json',
            ),
        );

        $result = (new NpmAuditor($runner))
            ->audit($this->repository());

        self::assertFalse($result->successful);
        self::assertSame([], $result->findings);
        self::assertNotNull($result->error);
        self::assertStringContainsString(
            'Unable to parse npm audit output',
            $result->error,
        );
    }

    public function testItReturnsFailureWhenNpmReturnsNoOutput(): void
    {
        $this->createNpmProject([]);

        $runner = new FakeProcessRunner(
            result: new ProcessResult(
                exitCode: 1,
                output: '',
            ),
        );

        $result = (new NpmAuditor($runner))
            ->audit($this->repository());

        self::assertFalse($result->successful);
        self::assertSame(
            'npm audit returned no output.',
            $result->error,
        );
    }

    public function testItReturnsFailureForNpmErrorResponse(): void
    {
        $this->createNpmProject([]);

        $runner = new FakeProcessRunner(
            result: new ProcessResult(
                exitCode: 1,
                output: json_encode(
                    [
                        'error' => [
                            'code' => 'ENOLOCK',
                            'summary' => 'This command requires an existing lockfile.',
                        ],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        $result = (new NpmAuditor($runner))
            ->audit($this->repository());

        self::assertFalse($result->successful);
        self::assertSame(
            'This command requires an existing lockfile.',
            $result->error,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'pine-npm-audit-tests-'
            . bin2hex(random_bytes(8));

        if (!mkdir($this->temporaryDirectory, 0777, true)) {
            throw new RuntimeException(
                'Unable to create temporary test directory.',
            );
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory
                . DIRECTORY_SEPARATOR
                . $item;

            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
