<?php

declare(strict_types=1);

namespace Pine\Repositories;

final readonly class Repository
{
    public function __construct(
        public string  $name,
        public string  $path,
        public ?string $branch = null,
        public ?int    $ahead = null,
        public ?int    $behind = null,
        public ?int    $lastCommitTimestamp = null,
        public ?bool   $dirty = null,
    )
    {
    }
}
