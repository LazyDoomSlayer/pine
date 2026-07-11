<?php

declare(strict_types=1);

namespace Pine\Tests\Commands;

use JsonException;
use PHPUnit\Framework\TestCase;
use Pine\Commands\RepositoriesListCommand;
use Pine\Console\Input;
use Pine\Console\JsonRenderer;
use Pine\Console\Output;
use Pine\Repositories\RepositoryInspector;
use Pine\Repositories\RepositoryScanner;
use Pine\Tests\Support\CreatesGitRepositories;
use RuntimeException;

final class RepositoriesListCommandTest extends TestCase
{
    use CreatesGitRepositories;

    private string $temporaryDirectory;

    public function testItListsDiscoveredRepositories(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'alpha-repository',
            dirty: false,
        );

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'beta-repository',
            dirty: true,
        );

        $result = $this->executeCommand([
            'pine',
            'repos:list',
            $repositoriesDirectory,
            '--depth=1',
        ]);

        self::assertSame(0, $result['exitCode']);

        self::assertStringContainsString(
            'alpha-repository',
            $result['output'],
        );

        self::assertStringContainsString(
            'beta-repository',
            $result['output'],
        );

        self::assertStringContainsString(
            'clean',
            $result['output'],
        );

        self::assertStringContainsString(
            'dirty',
            $result['output'],
        );

        self::assertStringContainsString(
            '2 repositories found.',
            $result['output'],
        );
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{
     *     exitCode: int,
     *     output: string
     * }
     */
    private function executeCommand(array $arguments): array
    {
        $command = new RepositoriesListCommand(
            scanner: new RepositoryScanner(),
            inspector: new RepositoryInspector(),
            jsonRenderer: new JsonRenderer(),
        );

        ob_start();

        try {
            $exitCode = $command->execute(
                input: new Input($arguments),
                output: new Output(),
            );

            $output = ob_get_contents();

            if ($output === false) {
                throw new RuntimeException(
                    'Unable to read buffered command output.',
                );
            }
        } finally {
            ob_end_clean();
        }

        return [
            'exitCode' => $exitCode,
            'output' => $output,
        ];
    }

    public function testItSortsRepositoriesByNameByDefault(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'zeta-repository',
            dirty: false,
        );

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'alpha-repository',
            dirty: false,
        );

        $result = $this->executeCommand([
            'pine',
            'repos:list',
            $repositoriesDirectory,
            '--depth=1',
        ]);

        $alphaPosition = strpos(
            $result['output'],
            'alpha-repository',
        );

        $zetaPosition = strpos(
            $result['output'],
            'zeta-repository',
        );

        self::assertNotFalse($alphaPosition);
        self::assertNotFalse($zetaPosition);
        self::assertLessThan($zetaPosition, $alphaPosition);
    }

    public function testItPrintsWarningWhenNoRepositoriesAreFound(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $result = $this->executeCommand([
            'pine',
            'repos:list',
            $repositoriesDirectory,
            '--depth=1',
        ]);

        self::assertSame(0, $result['exitCode']);

        self::assertStringContainsString(
            'No Git repositories found.',
            $result['output'],
        );
    }

    /**
     * @throws JsonException
     */
    public function testItReturnsJsonOutput(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'json-repository',
            dirty: true,
        );

        $result = $this->executeCommand([
            'pine',
            'repos:list',
            $repositoriesDirectory,
            '--depth=1',
            '--json',
        ]);

        self::assertSame(0, $result['exitCode']);

        $decoded = json_decode(
            $result['output'],
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertCount(1, $decoded);

        self::assertSame(
            'json-repository',
            $decoded[0]['name'],
        );

        self::assertSame(
            'main',
            $decoded[0]['branch'],
        );

        self::assertSame(
            0,
            $decoded[0]['ahead'],
        );

        self::assertSame(
            0,
            $decoded[0]['behind'],
        );

        self::assertTrue(
            $decoded[0]['dirty'],
        );

        self::assertArrayHasKey(
            'lastCommitAt',
            $decoded[0],
        );
    }
}
