<?php

declare(strict_types=1);

namespace Pine\Audit;

use Pine\Repositories\Repository;

final readonly class AuditResult
{
    /**
     * @param list<AuditFinding> $findings
     */
    public function __construct(
        public Repository $repository,
        public string     $ecosystem,
        public bool       $successful,
        public array      $findings = [],
        public ?string    $error = null,
    )
    {
    }

    public function hasVulnerabilities(): bool
    {
        return $this->findings !== [];
    }

    public function vulnerabilityCount(): int
    {
        return count($this->findings);
    }
}
