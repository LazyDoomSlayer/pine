<?php

declare(strict_types=1);

namespace Pine\Console;

final readonly class Input
{
    /**
     * @param array<int, string> $tokens
     */
    public function __construct(
        private array $tokens,
    ) {
    }

    public function commandName(): ?string
    {
        return $this->tokens[1] ?? null;
    }

    /**
     * @return list<string>
     */
    public function tokens(): array
    {
        return array_values(array_slice($this->tokens, 2));
    }
}
