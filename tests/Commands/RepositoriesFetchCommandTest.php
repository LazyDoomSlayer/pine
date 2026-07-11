<?php

declare(strict_types=1);

namespace Pine\Tests\Commands;

use PHPUnit\Framework\TestCase;
use Pine\Commands\RepositoriesFetchCommand;
use Pine\Console\Input;
use Pine\Console\Output;
use Pine\Process\ProcessRunner;
use Pine\Repositories\RepositoryFetcher;
use Pine\Repositories\RepositoryInspector;
use Pine\Repositories\RepositoryScanner;
use Pine\Tests\Support\CreatesGitRepositories;
use RuntimeException;

final class RepositoriesFetchCommandTest extends TestCase
{
    use CreatesGitRepositories;

    public function testItFetchesDiscoveredRepositories(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'alpha-repository',
            dirty: false,
        );

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'dirty-repository',
            dirty: true,
        );

        $result = $this->executeCommand([
            'pine',
            'repos:fetch',
            $repositoriesDirectory,
            '--depth=1',
            '--yes',
        ]);

        self::assertSame(0, $result['exitCode']);

        self::assertStringContainsString(
            'Repositories to Fetch',
            $result['output'],
        );

        self::assertStringContainsString(
            'alpha-repository',
            $result['output'],
        );

        self::assertStringContainsString(
            'dirty-repository',
            $result['output'],
        );

        self::assertStringContainsString(
            'Fetch Results',
            $result['output'],
        );

        self::assertStringContainsString(
            '2 processed, 0 failed.',
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
        $command = new RepositoriesFetchCommand(
            scanner: new RepositoryScanner(),
            inspector: new RepositoryInspector(),
            fetcher: new RepositoryFetcher(
                processRunner: new ProcessRunner(),
            ),
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

    public function testItFetchesDirtyRepositories(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'dirty-repository',
            dirty: true,
        );

        $result = $this->executeCommand([
            'pine',
            'repos:fetch',
            $repositoriesDirectory,
            '--depth=1',
            '--yes',
        ]);

        self::assertSame(0, $result['exitCode']);

        self::assertStringContainsString(
            'dirty-repository',
            $result['output'],
        );

        self::assertStringContainsString(
            'success',
            $result['output'],
        );

        self::assertStringContainsString(
            '1 processed, 0 failed.',
            $result['output'],
        );
    }

    public function testItReturnsFailureWhenFetchFails(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $repositoryPath = $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'broken-remote-repository',
            dirty: false,
        );

        $this->runGitCommand([
            'git',
            '-C',
            $repositoryPath,
            'remote',
            'set-url',
            'origin',
            '/path/that/does/not/exist.git',
        ]);

        $result = $this->executeCommand([
            'pine',
            'repos:fetch',
            $repositoriesDirectory,
            '--depth=1',
            '--yes',
        ]);

        self::assertSame(1, $result['exitCode']);

        self::assertStringContainsString(
            'broken-remote-repository',
            $result['output'],
        );

        self::assertStringContainsString(
            'failed',
            $result['output'],
        );

        self::assertStringContainsString(
            '1 processed, 1 failed.',
            $result['output'],
        );
    }

    public function testItPrintsWarningWhenNoRepositoriesAreFound(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $result = $this->executeCommand([
            'pine',
            'repos:fetch',
            $repositoriesDirectory,
            '--depth=1',
            '--yes',
        ]);

        self::assertSame(0, $result['exitCode']);

        self::assertStringContainsString(
            'No Git repositories found.',
            $result['output'],
        );
    }
}
