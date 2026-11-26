<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Audit;

use function activity;

use Illuminate\Database\Eloquent\Model;
use Shared\Domain\Ports\AuditPort;

/**
 * AuditPort implemented over spatie/laravel-activitylog ^5.
 *
 * Never log secrets. Callers pass only safe, business-meaningful properties.
 * The causer's display name is snapshotted deliberately so a later rename
 * does not rewrite historical attribution; it is not a secret.
 */
final readonly class SpatieActivityLogAdapter implements AuditPort
{
    public function log(
        string $event,
        ?object $subject = null,
        array $properties = [],
        ?object $causer = null,
        ?string $logName = null,
    ): void {
        $logger = activity($logName ?? 'default');

        if ($causer instanceof Model) {
            $logger->causedBy($causer);

            if (! array_key_exists('causer_name', $properties)) {
                $causerName = $causer->getAttribute('name');

                if (is_string($causerName) && $causerName !== '') {
                    $properties['causer_name'] = $causerName;
                }
            }
        } elseif ($causer !== null) {
            $logger->causedBy($causer);
        }

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        $logger
            ->withProperties($properties)
            ->event($event)
            ->log($event);
    }
}
