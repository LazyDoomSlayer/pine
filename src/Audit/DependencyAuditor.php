<?php

declare(strict_types=1);

namespace Pine\Audit;

use Pine\Repositories\Repository;

interface DependencyAuditor
{
    public function ecosystem(): string;

    public function supports(Repository $repository): bool;

    public function audit(Repository $repository): AuditResult;
}
