<?php

declare(strict_types=1);

namespace Pine\Console;

final class Output
{
    public function line(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }

    public function success(string $message): void
    {
        $this->line($this->color($message, '32'));
    }

    public function info(string $message): void
    {
        $this->line($this->color($message, '36'));
    }

    public function warning(string $message): void
    {
        $this->line($this->color($message, '33'));
    }

    public function error(string $message): void
    {
        fwrite(
            STDERR,
            $this->color($message, '31') . PHP_EOL,
        );
    }

    public function muted(string $message): void
    {
        $this->line($this->color($message, '90'));
    }

    private function color(string $message, string $code): string
    {
        return sprintf(
            "\033[%sm%s\033[0m",
            $code,
            $message,
        );
    }
}
