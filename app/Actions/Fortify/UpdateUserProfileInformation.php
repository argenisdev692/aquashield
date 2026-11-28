<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use App\Rules\AllowedEmailDomain;
use App\Rules\Username;
use App\Support\AuthRequestContext;
use App\Support\UserProfileInput;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Shared\Domain\Ports\AuditPort;
use Throwable;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(
        private readonly AuditPort $audit,
    ) {}

    /**
     * Validate and update the given user's profile information.
     *
     * `name` remains accepted as a backwards-compatible alias for the split
     * first/last pair; the account screen sends the canonical fields.
     *
     * @param array<string, mixed> $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $input = $this->normalise($input);

        $previousEmail = (string) $user->email;
        $emailChanged = Str::lower((string) ($input['email'] ?? '')) !== $previousEmail;

        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                new Username,
                Rule::unique('users', 'username')->ignore($user->getKey()),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                new AllowedEmailDomain,
                Rule::unique('users', 'email')->ignore($user->getKey()),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_2' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', 'string', 'in:male,female,other', 'max:50'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'terms_and_conditions' => ['sometimes', 'boolean'],
        ];

        if (array_key_exists('name', $input)) {
            $rules['name'] = ['required', 'string', 'max:255'];
        }

        /*
         * E-mail is the password-reset channel. Changing it from a hijacked
         * session must require the current password, or the attacker can move
         * the account to their own inbox and reset the password.
         */
        if ($emailChanged) {
            $rules['current_password'] = ['required', 'string', 'current_password:web'];
        }

        $validated = Validator::make($input, $rules)->validateWithBag('updateProfileInformation');

        unset($validated['name'], $validated['current_password']);
        $validated['email'] = Str::lower($validated['email']);

        $emailChanged = $validated['email'] !== $previousEmail;

        /*
         * `forceFill()` is typed by Larastan as accepting only a map of known
         * model properties. `$validated` is a dynamic, rule-shaped payload, so
         * the typed entry point is `setAttribute()`: the validation rules above
         * are the allowlist, and every key has already passed through them.
         * Absent keys stay absent, which is what a partial profile update means.
         */
        foreach ($validated as $attribute => $value) {
            $user->setAttribute($attribute, $value);
        }

        if ($emailChanged) {
            $user->setAttribute('email_verified_at', null);
        }

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            // The unique validator is a TOCTOU check; the database constraint is
            // the authority. Surface the race as a form error, not a 500.
            throw ValidationException::withMessages([
                'email' => 'That email or username is already in use.',
            ])->errorBag('updateProfileInformation');
        }

        $changed = array_values(array_diff(
            array_keys($user->getChanges()),
            ['updated_at'],
        ));

        if ($changed !== []) {
            $this->auditSafely('user.profile.updated', $user, ['changed' => $changed]);
        }

        if ($emailChanged) {
            $this->auditSafely('user.email.changed', $user, ['changed' => ['email']]);
            $this->alertOldAddress($previousEmail, $user);

            $user->sendEmailVerificationNotification();
        }
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function auditSafely(string $event, User $user, array $properties): void
    {
        try {
            $this->audit->log(
                $event,
                $user,
                [...$properties, ...AuthRequestContext::properties()],
                $user,
                'auth',
            );
        } catch (Throwable $exception) {
            // The profile write is already committed; an audit outage must not
            // turn a successful save into a 500.
            report($exception);
        }
    }

    private function alertOldAddress(string $previousEmail, User $user): void
    {
        try {
            SendSecurityAlert::dispatch(
                $previousEmail,
                $user->name,
                SecurityEvent::EmailChanged,
                ['new_email' => (string) $user->email],
            )->onQueue('emails');
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Normalise transport quirks before validation.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalise(array $input): array
    {
        $input = UserProfileInput::normalise($input);

        if (array_key_exists('name', $input) && ! array_key_exists('first_name', $input)) {
            $parts = preg_split('/\s+/', trim((string) $input['name'])) ?: [];
            $input['first_name'] = array_shift($parts) ?? '';
            $input['last_name'] = $parts === [] ? null : implode(' ', $parts);
        }

        if (array_key_exists('terms_and_conditions', $input)) {
            $input['terms_and_conditions'] = (bool) $input['terms_and_conditions'];
        }

        return $input;
    }
}
