<?php

declare(strict_types=1);

namespace Pine\Console;

final class ApplicationHelpRenderer
{
    /**
     * @param list<Command> $commands
     */
    public function render(array $commands): void
    {
        echo PHP_EOL;
        echo $this->renderBanner();
        echo PHP_EOL;
        echo 'A modern CLI framework for PHP.' . PHP_EOL;
        echo PHP_EOL;

        echo 'USAGE' . PHP_EOL;
        echo PHP_EOL;
        echo '  pine <command> [arguments] [options]' . PHP_EOL;
        echo PHP_EOL;

        echo 'AVAILABLE COMMANDS' . PHP_EOL;
        echo PHP_EOL;

        foreach ($commands as $command) {
            printf(
                "  %-20s %s%s",
                $command->getName(),
                $command->getDescription(),
                PHP_EOL,
            );
        }

        echo PHP_EOL;

        echo 'GLOBAL OPTIONS' . PHP_EOL;
        echo PHP_EOL;
        echo '  -h, --help           Display help information.' . PHP_EOL;
        echo '  -V, --version        Display the Pine version.' . PHP_EOL;
        echo PHP_EOL;

        echo 'COMMAND HELP' . PHP_EOL;
        echo PHP_EOL;
        echo '  Run a command with --help for detailed information:' . PHP_EOL;
        echo PHP_EOL;
        echo '  pine <command> --help' . PHP_EOL;
        echo PHP_EOL;
    }

    private function renderBanner(): string
    {
        return <<<'BANNER'
        ██████╗ ██╗███╗   ██╗███████╗
        ██╔══██╗██║████╗  ██║██╔════╝
        ██████╔╝██║██╔██╗ ██║█████╗
        ██╔═══╝ ██║██║╚██╗██║██╔══╝
        ██║     ██║██║ ╚████║███████╗
        ╚═╝     ╚═╝╚═╝  ╚═══╝╚══════╝
        BANNER;
    }
}
