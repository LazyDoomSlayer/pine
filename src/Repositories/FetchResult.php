<?php

declare(strict_types=1);

namespace Pine\Repositories;

final readonly class FetchResult
{
    public function __construct(
        public Repository $repository,
        public bool       $successful,
        public string     $message,
    )
    {
    }
}
