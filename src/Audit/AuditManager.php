<?php

declare(strict_types=1);

namespace Pine\Audit;

use Pine\Repositories\Repository;
use RuntimeException;

final readonly class AuditManager
{
    /**
     * Auditor order matters.
     *
     * @param list<DependencyAuditor> $auditors
     */
    public function __construct(
        private array $auditors,
    )
    {
    }

    public function supports(Repository $repository): bool
    {
        return $this->findAuditor($repository) !== null;
    }

    private function findAuditor(
        Repository $repository,
    ): ?DependencyAuditor
    {
        foreach ($this->auditors as $auditor) {
            if ($auditor->supports($repository)) {
                return $auditor;
            }
        }

        return null;
    }

    public function ecosystem(Repository $repository): ?string
    {
        return $this->findAuditor($repository)?->ecosystem();
    }

    public function audit(Repository $repository): AuditResult
    {
        $auditor = $this->findAuditor($repository);

        if ($auditor === null) {
            throw new RuntimeException(sprintf(
                'No dependency auditor supports repository "%s".',
                $repository->name,
            ));
        }

        return $auditor->audit($repository);
    }
}
