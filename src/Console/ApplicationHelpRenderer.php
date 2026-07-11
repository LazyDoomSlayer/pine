<?php

declare(strict_types=1);

namespace Pine\Console;

final class ApplicationHelpRenderer
{
    public function __construct(
        private readonly Output $output,
    )
    {
    }

    /**
     * @param list<Command> $commands
     */
    public function render(array $commands): void
    {
        $this->output->line();

        $this->output->info($this->renderBanner());
        $this->output->muted('A modern CLI framework for PHP.');

        $this->output->line();

        $this->output->info('USAGE');
        $this->output->line();
        $this->output->line('  pine <command> [arguments] [options]');

        $this->output->line();

        $this->output->info('AVAILABLE COMMANDS');
        $this->output->line();

        foreach ($commands as $command) {
            $this->output->line(sprintf(
                '  %-20s %s',
                $command->getName(),
                $command->getDescription(),
            ));
        }

        $this->output->line();

        $this->output->info('GLOBAL OPTIONS');
        $this->output->line();
        $this->output->line('  -h, --help           Display help information.');
        $this->output->line('  -V, --version        Display the Pine version.');

        $this->output->line();

        $this->output->info('COMMAND HELP');
        $this->output->line();
        $this->output->muted('  Run a command with --help for detailed information:');
        $this->output->line();
        $this->output->success('  pine <command> --help');

        $this->output->line();
    }

    private function renderBanner(): string
    {
        return <<<'BANNER'
██████╗ ██╗███╗   ██╗███████╗      ▄██▄
██╔══██╗██║████╗  ██║██╔════╝    ▄██████▄
██████╔╝██║██╔██╗ ██║█████╗     ██████████
██╔═══╝ ██║██║╚██╗██║██╔══╝      ▀██████▀
██║     ██║██║ ╚████║███████╗      ▐██▌
╚═╝     ╚═╝╚═╝  ╚═══╝╚══════╝       ▐▌
BANNER;
    }
}
