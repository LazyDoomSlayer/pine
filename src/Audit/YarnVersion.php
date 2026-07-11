<?php

declare(strict_types=1);

namespace Pine\Audit;

enum YarnVersion: string
{
    case Modern = 'yarn-modern';
    case Classic = 'yarn-classic';
}
