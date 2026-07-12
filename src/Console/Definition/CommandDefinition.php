<?php

declare(strict_types=1);

namespace Pine\Console\Definition;

final readonly class CommandDefinition
{
    /**
     * @param list<ArgumentDefinition> $arguments
     * @param list<OptionDefinition> $options
     * @param list<string> $examples
     */
    public function __construct(
        public string $name,
        public string $description,
        public array  $arguments = [],
        public array  $options = [],
        public array  $examples = [],
    )
    {
    }

    public function synopsis(): string
    {
        $parts = [$this->name];

        foreach ($this->arguments as $argument) {
            $parts[] = $argument->synopsis();
        }

        if ($this->options !== []) {
            $parts[] = '[options]';
        }

        return implode(' ', $parts);
    }
}
