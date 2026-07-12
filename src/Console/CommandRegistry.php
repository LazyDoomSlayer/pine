<?php

declare(strict_types=1);

namespace Pine\Console;

use Pine\Console\Command\Command;
use RuntimeException;

final class CommandRegistry
{
    /**
     * @var array<string, Command>
     */
    private array $commands = [];

    public function register(Command $command): void
    {
        $name = $command->definition()->name;

        if (isset($this->commands[$name])) {
            throw new RuntimeException(
                sprintf('Command "%s" is already registered.', $name),
            );
        }

        $this->commands[$name] = $command;
    }

    public function get(string $name): ?Command
    {
        return $this->commands[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * @return array<string, Command>
     */
    public function all(): array
    {
        return $this->commands;
    }
}
