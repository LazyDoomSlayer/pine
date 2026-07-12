<?php

declare(strict_types=1);

namespace Pine\Console;

use Pine\Console\Definition\CommandDefinition;

final readonly class CommandHelpRenderer
{
    public function __construct(
        private Output $output,
    )
    {
    }

    public function render(CommandDefinition $definition): void
    {
        $this->output->line($definition->description);
        $this->output->line();

        $this->output->info('Usage:');
        $this->output->line(
            "  pine {$definition->synopsis()}",
        );

        if ($definition->arguments !== []) {
            $this->renderArguments($definition);
        }

        if ($definition->options !== []) {
            $this->renderOptions($definition);
        }

        if ($definition->examples !== []) {
            $this->renderExamples($definition);
        }
    }

    private function renderArguments(
        CommandDefinition $definition,
    ): void
    {
        $this->output->line();
        $this->output->info('Arguments:');

        $rows = [];

        foreach ($definition->arguments as $argument) {
            $description = $argument->description;

            if ($argument->default !== null) {
                $description .= sprintf(
                    ' [default: %s]',
                    $argument->default,
                );
            }

            $rows[] = [
                $argument->name,
                $description,
            ];
        }

        $this->output->table(
            headers: ['ARGUMENT', 'DESCRIPTION'],
            rows: $rows,
        );
    }

    private function renderOptions(
        CommandDefinition $definition,
    ): void
    {
        $this->output->line();
        $this->output->info('Options:');

        $rows = [];

        foreach ($definition->options as $option) {
            $description = $option->description;

            if ($option->default !== null) {
                $description .= sprintf(
                    ' [default: %s]',
                    $option->default,
                );
            }

            $rows[] = [
                $option->synopsis(),
                $description,
            ];
        }

        $this->output->table(
            headers: ['OPTION', 'DESCRIPTION'],
            rows: $rows,
        );
    }

    private function renderExamples(
        CommandDefinition $definition,
    ): void
    {
        $this->output->line();
        $this->output->info('Examples:');

        foreach ($definition->examples as $example) {
            $this->output->line("  {$example}");
        }
    }
}
