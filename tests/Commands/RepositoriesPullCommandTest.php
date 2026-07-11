<?php

declare(strict_types=1);

namespace Pine\Tests\Commands;

use PHPUnit\Framework\TestCase;
use Pine\Commands\RepositoriesPullCommand;
use Pine\Console\Input;
use Pine\Console\Output;
use Pine\Process\ProcessRunner;
use Pine\Repositories\RepositoryInspector;
use Pine\Repositories\RepositoryPuller;
use Pine\Repositories\RepositoryScanner;
use Pine\Tests\Support\CreatesGitRepositories;
use RuntimeException;

final class RepositoriesPullCommandTest extends TestCase
{
    use CreatesGitRepositories;

    public function testItReportsAlreadyUpToDateRepository(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'up-to-date-repository',
            dirty: false,
        );

        $result = $this->executeCommand([
            'pine',
            'repos:pull',
            $repositoriesDirectory,
            '--depth=1',
            '--yes',
        ]);

        self::assertSame(0, $result['exitCode']);

        self::assertStringContainsString(
            'Repositories to Pull',
            $result['output'],
        );

        self::assertStringContainsString(
            'Pull Results',
            $result['output'],
        );

        self::assertStringContainsString(
            'up-to-date-repository',
            $result['output'],
        );

        self::assertStringContainsString(
            'already up to date',
            $result['output'],
        );

        self::assertStringContainsString(
            '1 processed, 0 skipped, 0 failed.',
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
        $command = new RepositoriesPullCommand(
            scanner: new RepositoryScanner(),
            inspector: new RepositoryInspector(),
            puller: new RepositoryPuller(
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

    public function testItSkipsDirtyRepositoryByDefault(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'dirty-repository',
            dirty: true,
        );

        $result = $this->executeCommand([
            'pine',
            'repos:pull',
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
            'skipped',
            $result['output'],
        );

        self::assertStringContainsString(
            'dirty worktree',
            $result['output'],
        );

        self::assertStringContainsString(
            '1 processed, 1 skipped, 0 failed.',
            $result['output'],
        );
    }

    public function testItAllowsDirtyRepositoryWhenOptionIsProvided(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'dirty-repository',
            dirty: true,
        );

        $result = $this->executeCommand([
            'pine',
            'repos:pull',
            $repositoriesDirectory,
            '--depth=1',
            '--yes',
            '--include-dirty',
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
            'already up to date',
            $result['output'],
        );

        self::assertStringContainsString(
            '1 processed, 0 skipped, 0 failed.',
            $result['output'],
        );
    }

    public function testItPullsRemoteCommit(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $repositoryPath = $this->createRepository(
            repositoriesDirectory: $repositoriesDirectory,
            name: 'behind-repository',
            dirty: false,
        );

        $this->createRemoteCommit(
            repositoryPath: $repositoryPath,
            fileName: 'remote-change.txt',
            contents: 'Remote change.',
        );

        $result = $this->executeCommand([
            'pine',
            'repos:pull',
            $repositoriesDirectory,
            '--depth=1',
            '--yes',
        ]);

        self::assertSame(0, $result['exitCode']);

        self::assertStringContainsString(
            'behind-repository',
            $result['output'],
        );

        self::assertStringContainsString(
            'success',
            $result['output'],
        );

        self::assertStringContainsString(
            '1 processed, 0 skipped, 0 failed.',
            $result['output'],
        );

        self::assertFileExists(
            $repositoryPath
            . DIRECTORY_SEPARATOR
            . 'remote-change.txt',
        );
    }

    private function createRemoteCommit(
        string $repositoryPath,
        string $fileName,
        string $contents,
    ): void
    {
        $clonePath = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . 'remote-worktree-'
            . bin2hex(random_bytes(4));

        $remoteUrl = trim($this->runGitCommandWithOutput([
            'git',
            '-C',
            $repositoryPath,
            'remote',
            'get-url',
            'origin',
        ]));

        $this->runGitCommand([
            'git',
            'clone',
            $remoteUrl,
            $clonePath,
        ]);

        $this->runGitCommand([
            'git',
            '-C',
            $clonePath,
            'config',
            'user.name',
            'Pine Tests',
        ]);

        $this->runGitCommand([
            'git',
            '-C',
            $clonePath,
            'config',
            'user.email',
            'pine-tests@example.com',
        ]);

        $this->runGitCommand([
            'git',
            '-C',
            $clonePath,
            'checkout',
            'main',
        ]);

        file_put_contents(
            $clonePath . DIRECTORY_SEPARATOR . $fileName,
            $contents . PHP_EOL,
        );

        $this->runGitCommand([
            'git',
            '-C',
            $clonePath,
            'add',
            $fileName,
        ]);

        $this->runGitCommand([
            'git',
            '-C',
            $clonePath,
            'commit',
            '-m',
            'Add remote change',
        ]);

        $this->runGitCommand([
            'git',
            '-C',
            $clonePath,
            'push',
            'origin',
            'main',
        ]);

        $this->runGitCommand([
            'git',
            '-C',
            $repositoryPath,
            'fetch',
            'origin',
        ]);
    }

    /**
     * @param list<string> $parts
     */
    private function runGitCommandWithOutput(array $parts): string
    {
        $command = implode(
            ' ',
            array_map(
                static fn(string $part): string => escapeshellarg($part),
                $parts,
            ),
        );

        $output = [];
        $exitCode = 0;

        exec(
            $command . ' 2>&1',
            $output,
            $exitCode,
        );

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                "Command failed: %s\n%s",
                $command,
                implode(PHP_EOL, $output),
            ));
        }

        return implode(PHP_EOL, $output);
    }

    public function testItReturnsFailureWhenPullFails(): void
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

        $this->runGitCommand([
            'git',
            '-C',
            $repositoryPath,
            'update-ref',
            'refs/remotes/origin/main',
            'HEAD~0',
        ]);

        file_put_contents(
            $repositoryPath . DIRECTORY_SEPARATOR . 'local-change.txt',
            'Local change.' . PHP_EOL,
        );

        $this->runGitCommand([
            'git',
            '-C',
            $repositoryPath,
            'add',
            'local-change.txt',
        ]);

        $this->runGitCommand([
            'git',
            '-C',
            $repositoryPath,
            'commit',
            '-m',
            'Local commit',
        ]);

        $result = $this->executeCommand([
            'pine',
            'repos:pull',
            $repositoriesDirectory,
            '--depth=1',
            '--yes',
        ]);

        self::assertSame(0, $result['exitCode']);

        self::assertStringContainsString(
            'broken-remote-repository',
            $result['output'],
        );
    }

    public function testItPrintsWarningWhenNoRepositoriesAreFound(): void
    {
        $repositoriesDirectory = $this->createRepositoriesDirectory();

        $result = $this->executeCommand([
            'pine',
            'repos:pull',
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
