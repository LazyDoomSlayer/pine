<?php

declare(strict_types=1);

namespace Pine\Console\Definition;

final readonly class OptionDefinition
{
    public function __construct(
        public string  $name,
        public string  $description,
        public bool    $acceptsValue = false,
        public ?string $shortcut = null,
        public ?string $default = null,
        public ?string $valueName = null,
    )
    {
    }

    public function synopsis(): string
    {
        $name = "--{$this->name}";

        if ($this->shortcut !== null) {
            $name = "-{$this->shortcut}, {$name}";
        }

        if ($this->acceptsValue) {
            $valueName = $this->valueName ?? strtoupper($this->name);

            $name .= "={$valueName}";
        }

        return $name;
    }
}
