<?php

declare(strict_types=1);

namespace Pine\Tests\Commands;

use PHPUnit\Framework\TestCase;
use Pine\Commands\RepositoriesStatusCommand;
use Pine\Console\Input;
use Pine\Console\JsonRenderer;
use Pine\Console\Output;
use Pine\Repositories\RepositoryInspector;
use Pine\Repositories\RepositoryScanner;
use Pine\Tests\Support\CreatesGitRepositories;
use RuntimeException;

final class RepositoriesStatusCommandTest extends TestCase
{
    use CreatesGitRepositories;

    private string $temporaryDirectory;

    public function testItShowsDirtyRepositories(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'clean-repository',
            dirty: false,
        );

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'dirty-repository',
            dirty: true,
        );

        $result = $this->executeCommand([
            'pine',
            'repos:status',
            $repositoriesDirectory,
            '--depth=1',
        ]);

        self::assertSame(0, $result['exitCode']);

        self::assertStringContainsString(
            'dirty-repository',
            $result['output'],
        );

        self::assertStringContainsString(
            'dirty',
            $result['output'],
        );

        self::assertStringNotContainsString(
            'clean-repository',
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
        $command = new RepositoriesStatusCommand(
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

    public function testItPrintsSuccessWhenAllRepositoriesAreClean(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'clean-repository',
            dirty: false,
        );

        $result = $this->executeCommand([
            'pine',
            'repos:status',
            $repositoriesDirectory,
            '--depth=1',
        ]);

        self::assertSame(0, $result['exitCode']);

        self::assertStringContainsString(
            'All repositories are clean and synchronized.',
            $result['output'],
        );

        self::assertStringNotContainsString(
            'clean-repository',
            $result['output'],
        );
    }

    public function testItReturnsJsonOutput(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'dirty-repository',
            dirty: true,
        );

        $result = $this->executeCommand([
            'pine',
            'repos:status',
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
            'dirty-repository',
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
    }
}
