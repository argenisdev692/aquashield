<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Data;

use App\Models\User;
use Modules\ActivityLog\Models\ActivityEntry;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One audit entry, shaped for the panel.
 *
 * `properties` is an allowlist, not the raw payload. The full record stays in
 * the database; the screen never echoes a token, a signed URL or a field the
 * audit write did not explicitly classify as safe.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ActivityEntryData extends Data
{
    /**
     * @param array<string, string|int> $properties
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $logName,
        public readonly string $event,
        public readonly string $description,
        public readonly ?string $causerName,
        public readonly ?string $subjectType,
        public readonly array $properties,
        public readonly string $createdAt,
    ) {}

    public static function fromModel(ActivityEntry $entry): self
    {
        $causer = $entry->causer;

        return new self(
            uuid: (string) $entry->uuid,
            logName: (string) ($entry->log_name ?? 'default'),
            event: (string) ($entry->event ?? ''),
            description: (string) $entry->description,
            causerName: $causer instanceof User ? $causer->name : null,
            subjectType: self::subjectType($entry->subject_type),
            properties: self::safeProperties($entry->properties?->all() ?? []),
            createdAt: $entry->created_at?->format('Y-m-d H:i:s') ?? '',
        );
    }

    private static function subjectType(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $parts = explode('\\', $type);

        return end($parts) ?: null;
    }

    /**
     * @param array<string, mixed> $properties
     * @return array<string, string|int>
     */
    private static function safeProperties(array $properties): array
    {
        $safe = [];

        foreach (['disk', 'filename', 'size_bytes', 'reason', 'requested_by'] as $key) {
            $value = $properties[$key] ?? null;

            if (is_string($value) || is_int($value)) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
