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
    public function arguments(): array
    {
        return array_values(array_filter(
            $this->commandTokens(),
            static fn (string $token): bool => !str_starts_with($token, '--'),
        ));
    }

    /**
     * @return list<string>
     */
    public function options(): array
    {
        return array_values(array_filter(
            $this->commandTokens(),
            static fn (string $token): bool => str_starts_with($token, '--'),
        ));
    }

    /**
     * @return list<string>
     */
    public function tokens(): array
    {
        return $this->commandTokens();
    }

    /**
     * @return list<string>
     */
    private function commandTokens(): array
    {
        return array_values(array_slice($this->tokens, 2));
    }
}
