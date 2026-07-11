<?php

declare(strict_types=1);

namespace Pine\Repositories;

final readonly class PullResult
{
    public function __construct(
        public Repository $repository,
        public string     $status,
        public string     $message,
    )
    {
    }

    public function successful(): bool
    {
        return $this->status === 'success';
    }

    public function failed(): bool
    {
        return $this->status === 'failed';
    }

    public function skipped(): bool
    {
        return $this->status === 'skipped';
    }
}
