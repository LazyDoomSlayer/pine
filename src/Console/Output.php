<?php

declare(strict_types=1);

namespace Pine\Console;

final class Output
{
    public function success(string $message): void
    {
        $this->line($this->color($message, '32'));
    }

    public function line(string $message = ''): void
    {
        echo $message . PHP_EOL;
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
        $this->line($this->color($message, '33'));
    }

    public function error(string $message): void
    {
        fwrite(
            STDERR,
            $this->color($message, '31') . PHP_EOL,
        );
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
            static fn(string $header): int => strlen($header),
            $headers,
        );

        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                $columnWidths[$index] = max(
                    $columnWidths[$index] ?? 0,
                    strlen($value),
                );
            }
        }

        return $columnWidths;
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

            $cells[] = str_pad($value, $width);
        }

        $this->line('  ' . implode('  ', $cells));
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
        $this->line($this->color($message, '90'));
    }
}
