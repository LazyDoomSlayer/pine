<?php

declare(strict_types=1);

namespace Pine\Console\Definition;

final readonly class ArgumentDefinition
{
    public function __construct(
        public string  $name,
        public string  $description,
        public bool    $required = false,
        public ?string $default = null,
    )
    {
    }

    public function synopsis(): string
    {
        return $this->required
            ? "<{$this->name}>"
            : "[{$this->name}]";
    }
}
