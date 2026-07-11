<?php

declare(strict_types=1);

namespace Pine\Repositories;

use FilesystemIterator;
use InvalidArgumentException;

final class RepositoryScanner
{
    /**
     * @return list<Repository>
     */
    public function scan(
        string $directory,
        int    $depth = 1,
    ): array
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException(sprintf(
                'Directory "%s" does not exist.',
                $directory,
            ));
        }

        $repositories = [];

        $iterator = new FilesystemIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS,
        );

        foreach ($iterator as $item) {
            if (!$item->isDir()) {
                continue;
            }

            $gitDirectory = $item->getPathname() . DIRECTORY_SEPARATOR . '.git';

            if (!is_dir($gitDirectory)) {
                continue;
            }

            $repositories[] = new Repository(
                name: $item->getFilename(),
                path: $item->getPathname(),
            );
        }

        return $repositories;
    }
}
