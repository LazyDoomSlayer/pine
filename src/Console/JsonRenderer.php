<?php

declare(strict_types=1);

namespace Pine\Console;

use JsonException;

final class JsonRenderer
{
    /**
     * @param array<array-key, mixed> $data
     *
     * @throws JsonException
     */
    public function render(array $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR,
        );
    }
}
