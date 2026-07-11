<?php

declare(strict_types=1);

namespace Pine\Console;

final class CommandRegistry
{
    /**
     * @var array<string, Command>
     */
    private array $commands = [];

    public function add(Command $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    public function find(string $name): ?Command
    {
        return $this->commands[$name] ?? null;
    }

    /**
     * @return list<Command>
     */
    public function all(): array
    {
        return array_values($this->commands);
    }
}
