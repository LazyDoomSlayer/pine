<?php

declare(strict_types=1);

namespace Pine\Audit;

final readonly class AuditFinding
{
    /**
     * @param list<string> $dependencyPaths
     * @param list<string> $cves
     */
    public function __construct(
        public string  $package,
        public string  $severity,
        public ?string $installedVersion,
        public string  $affectedRange,
        public ?string $patchedRange,
        public string  $title,
        public string  $advisoryId,
        public ?string $url,
        public ?string $recommendation,
        public string  $dependencyType,
        public array   $dependencyPaths = [],
        public array   $cves = [],
        public ?float  $cvssScore = null,
        public bool    $direct = false,
    )
    {
    }

    public function reference(): string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        if ($this->cves !== []) {
            return $this->cves[0];
        }

        return $this->advisoryId;
    }
}
