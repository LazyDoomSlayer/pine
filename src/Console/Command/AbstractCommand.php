<?php

declare(strict_types=1);

namespace Pine\Console\Command;

use Pine\Console\Definition\CommandDefinition;
use Pine\Console\Definition\OptionDefinition;

abstract class AbstractCommand implements Command
{
    final public function definition(): CommandDefinition
    {
        $definition = $this->configure();

        return new CommandDefinition(
            name: $definition->name,
            description: $definition->description,
            arguments: $definition->arguments,
            options: [
                ...$definition->options,
                new OptionDefinition(
                    name: 'help',
                    description: 'Display help for this command.',
                    shortcut: 'h',
                ),
            ],
            examples: $definition->examples,
        );
    }

    abstract protected function configure(): CommandDefinition;
}
