<?php

declare(strict_types=1);

namespace Pine\Console;

final readonly class Input
{
    /**
     * @param array<int, string> $tokens
     */
    public function __construct(
        private array $tokens,
    )
    {
    }

    public function argument(int $index): ?string
    {
        return $this->arguments()[$index] ?? null;
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return array_values(array_filter(
            $this->commandTokens(),
            static fn(string $token): bool => !str_starts_with($token, '--'),
        ));
    }

    /**
     * @return list<string>
     */
    private function commandTokens(): array
    {
        return array_values(array_slice($this->tokens, 2));
    }

    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options());
    }

    /**
     * @return array<string, bool|string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->optionTokens() as $token) {
            $option = substr($token, 2);

            if (!str_contains($option, '=')) {
                $options[$option] = true;

                continue;
            }

            [$name, $value] = explode('=', $option, 2);

            $options[$name] = $value;
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function optionTokens(): array
    {
        return array_values(array_filter(
            $this->commandTokens(),
            static fn(string $token): bool => str_starts_with($token, '--'),
        ));
    }

    public function option(string $name): bool|string|null
    {
        return $this->options()[$name] ?? null;
    }

    public function commandName(): ?string
    {
        return $this->tokens[1] ?? null;
    }

    /**
     * @return list<string>
     */
    public function tokens(): array
    {
        return $this->commandTokens();
    }

    public function confirm(
        string $question,
        bool   $default = false,
    ): bool
    {
        $suffix = $default ? ' [Y/n] ' : ' [y/N] ';

        fwrite(STDOUT, $question . $suffix);

        $answer = fgets(STDIN);

        if ($answer === false) {
            return $default;
        }

        $answer = strtolower(trim($answer));

        if ($answer === '') {
            return $default;
        }

        return in_array($answer, ['y', 'yes'], true);
    }
}
