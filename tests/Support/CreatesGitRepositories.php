<?php

declare(strict_types=1);

namespace Pine\Tests\Support;

use RuntimeException;

trait CreatesGitRepositories
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'pine-tests-'
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

    protected function createRepositoriesDirectory(): string
    {
        $directory = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . 'repositories';

        if (!mkdir($directory, 0777, true)) {
            throw new RuntimeException(
                'Unable to create repositories directory.',
            );
        }

        return $directory;
    }

    protected function createRepository(
        string $repositoriesDirectory,
        string $name,
        bool   $dirty,
    ): void
    {
        $remotesDirectory = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . 'remotes';

        if (
            !is_dir($remotesDirectory)
            && !mkdir($remotesDirectory, 0777, true)
        ) {
            throw new RuntimeException(
                'Unable to create remotes directory.',
            );
        }

        $remotePath = $remotesDirectory
            . DIRECTORY_SEPARATOR
            . $name
            . '.git';

        $repositoryPath = $repositoriesDirectory
            . DIRECTORY_SEPARATOR
            . $name;

        $this->runGitCommand([
            'git',
            'init',
            '--bare',
            $remotePath,
        ]);

        $this->runGitCommand([
            'git',
            'clone',
            $remotePath,
            $repositoryPath,
        ]);

        $this->runGitCommand([
            'git',
            '-C',
            $repositoryPath,
            'config',
            'user.name',
            'Pine Tests',
        ]);

        $this->runGitCommand([
            'git',
            '-C',
            $repositoryPath,
            'config',
            'user.email',
            'pine-tests@example.com',
        ]);

        $this->runGitCommand([
            'git',
            '-C',
            $repositoryPath,
            'checkout',
            '-b',
            'main',
        ]);

        file_put_contents(
            $repositoryPath . DIRECTORY_SEPARATOR . 'README.md',
            "# {$name}" . PHP_EOL,
        );

        $this->runGitCommand([
            'git',
            '-C',
            $repositoryPath,
            'add',
            'README.md',
        ]);

        $this->runGitCommand([
            'git',
            '-C',
            $repositoryPath,
            'commit',
            '-m',
            'Initial commit',
        ]);

        $this->runGitCommand([
            'git',
            '-C',
            $repositoryPath,
            'push',
            '--set-upstream',
            'origin',
            'main',
        ]);

        if ($dirty) {
            file_put_contents(
                $repositoryPath
                . DIRECTORY_SEPARATOR
                . 'uncommitted.txt',
                'This file is not committed.' . PHP_EOL,
            );
        }
    }

    /**
     * @param list<string> $parts
     */
    protected function runGitCommand(array $parts): void
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
    }
}
