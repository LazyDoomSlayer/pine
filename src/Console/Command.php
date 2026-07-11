<?php

declare(strict_types=1);

namespace Pine\Console;

abstract class Command
{
    abstract public function execute(): int;
}
