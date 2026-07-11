<?php

declare(strict_types=1);

namespace Pine\Repositories;

final readonly class Repository
{
    public function __construct(
        public string $name,
        public string $path,
    )
    {
    }
}
