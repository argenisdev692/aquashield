<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Shared\Infrastructure\Company\CompanyProfile;

/**
 * The office copy of a lead/appointment notification: the company mailbox from
 * the `company_data` row is the primary recipient and every ADMIN user is CC'd.
 *
 * One definition for the listeners that notify the office. A missing company
 * profile degrades to the app's MAIL_FROM_ADDRESS (via {@see CompanyProfile}),
 * and a deployment with no ADMIN role simply sends no copy.
 */
final readonly class OfficeMailRecipients
{
    private const string ADMIN_ROLE = 'ADMIN';

    /**
     * @param list<string> $cc
     */
    private function __construct(
        public string $to,
        public array $cc,
    ) {}

    public static function resolve(): ?self
    {
        $to = self::validEmail(CompanyProfile::data()['support_email']);
        $seen = $to === null ? [] : [mb_strtolower($to) => true];
        $cc = [];

        foreach (self::adminEmails() as $email) {
            $key = mb_strtolower($email);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $cc[] = $email;
        }

        if ($to === null) {
            if ($cc === []) {
                return null;
            }

            $to = $cc[0];
            $cc = array_slice($cc, 1);
        }

        return new self($to, $cc);
    }

    /**
     * @return list<string>
     */
    private static function adminEmails(): array
    {
        $emails = User::query()
            ->whereHas('roles', static fn (Builder $query): Builder => $query->where('name', self::ADMIN_ROLE))
            ->whereNotNull('email')
            ->orderBy('id')
            ->pluck('email');

        $seen = [];
        $valid = [];

        foreach ($emails as $email) {
            $normalized = self::validEmail($email);

            if ($normalized === null) {
                continue;
            }

            $key = mb_strtolower($normalized);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $valid[] = $normalized;
        }

        return $valid;
    }

    private static function validEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = trim($email);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }
}
