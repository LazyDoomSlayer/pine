<?php

declare(strict_types=1);

namespace Pine\Console;

abstract class Command
{
    abstract public function getName(): string;

    abstract public function getDescription(): string;

    abstract public function execute(Input $input, Output $output): int;
}
