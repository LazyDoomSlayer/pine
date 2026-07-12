<?php

declare(strict_types=1);

namespace Pine\Console\Command;

use Pine\Console\Definition\CommandDefinition;
use Pine\Console\Input;
use Pine\Console\Output;

interface Command
{
    public function definition(): CommandDefinition;

    public function execute(Input $input, Output $output): int;
}
