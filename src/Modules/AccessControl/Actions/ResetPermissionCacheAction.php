<?php

declare(strict_types=1);

namespace Modules\AccessControl\Actions;

use App\Models\User;
use Shared\Domain\Ports\AuditPort;
use Spatie\Permission\PermissionRegistrar;

/**
 * Invalidates Spatie's cached permission registry after an out-of-band change.
 *
 * The package already flushes on its own model writes; this action exists for a
 * seeder, a direct SQL change or an operator-visible "flush" control.
 */
final readonly class ResetPermissionCacheAction
{
    public function __construct(
        private AuditPort $audit,
        private PermissionRegistrar $registrar,
    ) {}

    public function execute(User $actor): void
    {
        $this->registrar->forgetCachedPermissions();

        $this->audit->log('access.cache_reset', null, [], $actor, 'access-control');
    }
}
