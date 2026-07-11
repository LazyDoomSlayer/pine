<?php

declare(strict_types=1);

namespace Pine\Console;

final class Output
{
    public function success(string $message): void
    {
        $this->line($this->successText($message));
    }

    public function line(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }

    public function successText(string $message): string
    {
        return $this->color($message, '32');
    }

    private function color(string $message, string $code): string
    {
        return sprintf(
            "\033[%sm%s\033[0m",
            $code,
            $message,
        );
    }

    public function warning(string $message): void
    {
        $this->line($this->warningText($message));
    }

    public function warningText(string $message): string
    {
        return $this->color($message, '33');
    }

    public function error(string $message): void
    {
        fwrite(
            STDERR,
            $this->errorText($message) . PHP_EOL,
        );
    }

    public function errorText(string $message): string
    {
        return $this->color($message, '31');
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    public function table(
        array   $headers,
        array   $rows,
        bool    $numbered = false,
        ?string $title = null,
        ?string $footer = null,
    ): void
    {
        if ($title !== null) {
            $this->info($title);
            $this->line();
        }

        if ($numbered) {
            $headers = ['#', ...$headers];

            $rows = array_map(
                static fn(array $row, int $index): array => [
                    (string)($index + 1),
                    ...$row,
                ],
                $rows,
                array_keys($rows),
            );
        }

        $columnWidths = $this->calculateColumnWidths(
            headers: $headers,
            rows: $rows,
        );

        $this->renderTableRow($headers, $columnWidths);
        $this->renderTableSeparator($columnWidths);

        foreach ($rows as $row) {
            $this->renderTableRow($row, $columnWidths);
        }

        if ($footer !== null) {
            $this->line();
            $this->muted($footer);
        }
    }

    public function info(string $message): void
    {
        $this->line($this->color($message, '36'));
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     *
     * @return list<int>
     */
    private function calculateColumnWidths(
        array $headers,
        array $rows,
    ): array
    {
        $columnWidths = array_map(
            fn(string $header): int => $this->displayWidth($header),
            $headers,
        );

        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                $columnWidths[$index] = max(
                    $columnWidths[$index] ?? 0,
                    $this->displayWidth($value),
                );
            }
        }

        return $columnWidths;
    }

    private function displayWidth(string $value): int
    {
        $plainValue = preg_replace(
            '/\033\[[0-9;]*m/',
            '',
            $value,
        );

        if ($plainValue === null) {
            $plainValue = $value;
        }

        return mb_strwidth($plainValue, 'UTF-8');
    }

    /**
     * @param list<string> $row
     * @param list<int> $columnWidths
     */
    private function renderTableRow(
        array $row,
        array $columnWidths,
    ): void
    {
        $cells = [];

        foreach ($columnWidths as $index => $width) {
            $value = $row[$index] ?? '';

            $cells[] = $this->padRight(
                $value,
                $width,
            );
        }

        $this->line('  ' . implode('  ', $cells));
    }

    private function padRight(
        string $value,
        int    $width,
    ): string
    {
        return $value . str_repeat(
                ' ',
                max(
                    0,
                    $width - $this->displayWidth($value),
                ),
            );
    }

    /**
     * @param list<int> $columnWidths
     */
    private function renderTableSeparator(array $columnWidths): void
    {
        $segments = array_map(
            static fn(int $width): string => str_repeat('─', $width),
            $columnWidths,
        );

        $this->muted('  ' . implode('  ', $segments));
    }

    public function muted(string $message): void
    {
        $this->line($this->mutedText($message));
    }

    public function mutedText(string $message): string
    {
        return $this->color($message, '90');
    }

    public function infoText(string $message): string
    {
        return $this->color($message, '36');
    }

    public function write(string $message): void
    {
        echo $message;
    }

    public function clearLine(): void
    {
        echo "\r\033[2K";
    }
}
