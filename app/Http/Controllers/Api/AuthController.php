<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Auth\AuthTokenData;
use App\Data\Auth\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Models\User;
use App\Support\AuthRequestContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\StoragePort;
use Throwable;

/**
 * Sanctum token surface for mobile/external clients.
 *
 * The web stays on Fortify sessions; this controller is deliberately a second
 * transport over the same user, password and TOTP rules. It never puts a raw
 * Eloquent model on the wire (Spatie Data allowlist) and never logs a password
 * or a token.
 */
final class AuthController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationProvider $twoFactor,
        private readonly AuditPort $audit,
        private readonly StoragePort $storage,
    ) {}

    public function login(LoginRequest $request): AuthTokenData
    {
        $email = Str::lower($request->string('email')->toString());

        if (! Auth::validate([
            'email' => $email,
            'password' => $request->string('password')->toString(),
        ])) {
            $this->auditFailure($email);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $this->auditFailure($email);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $this->auditFailure($email);

            throw ValidationException::withMessages([
                'email' => __('Your email address is not verified. Check your inbox for the verification link, then sign in again.'),
            ]);
        }

        $this->assertTwoFactor($user, $request);

        $device = trim($request->string('device_name')->toString()) ?: 'api';
        $expiresAt = $this->expiryFor($user);

        $newToken = DB::transaction(function () use ($user, $device, $expiresAt) {
            // One active token per device name: a second login from the same
            // device rotates the credential instead of accumulating tokens.
            $user->tokens()->where('name', $device)->delete();

            return $user->createToken($device, ['*'], $expiresAt);
        });

        $this->audit('auth.api.login', $user, [
            'device_name' => $device,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return new AuthTokenData(
            token: $newToken->plainTextToken,
            tokenType: 'Bearer',
            expiresAt: $expiresAt->toIso8601String(),
            user: UserData::fromModel($user, $this->storage),
        );
    }

    public function refresh(Request $request): AuthTokenData
    {
        if ($request->bearerToken() === null) {
            abort(401);
        }

        $user = $this->authenticatedUser($request);
        $current = $user->currentAccessToken();
        $expiresAt = $this->expiryFor($user);

        $newToken = DB::transaction(function () use ($user, $current, $expiresAt) {
            $name = (string) $current->name;
            $abilities = $current->abilities === null ? ['*'] : (array) $current->abilities;

            $current->delete();

            return $user->createToken($name, $abilities, $expiresAt);
        });

        $this->audit('auth.api.refresh', $user, [
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return new AuthTokenData(
            token: $newToken->plainTextToken,
            tokenType: 'Bearer',
            expiresAt: $expiresAt->toIso8601String(),
            user: UserData::fromModel($user, $this->storage),
        );
    }

    public function me(Request $request): UserData
    {
        return UserData::fromModel($this->authenticatedUser($request), $this->storage);
    }

    public function logout(Request $request): Response
    {
        if ($request->bearerToken() === null) {
            abort(401);
        }

        $user = $this->authenticatedUser($request);
        $current = $user->currentAccessToken();
        $current->delete();

        $this->audit('auth.api.logout', $user);

        return response()->noContent();
    }

    private function assertTwoFactor(User $user, LoginRequest $request): void
    {
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return;
        }

        $recoveryCode = trim($request->string('recovery_code')->toString());

        if ($recoveryCode !== '') {
            if (in_array($recoveryCode, $user->recoveryCodes(), true)) {
                $user->replaceRecoveryCode($recoveryCode);

                return;
            }

            throw ValidationException::withMessages([
                'recovery_code' => __('The provided two factor recovery code was invalid.'),
            ]);
        }

        $code = trim($request->string('two_factor_code')->toString());

        if ($code === '') {
            throw ValidationException::withMessages([
                'two_factor_code' => __('Two-factor authentication is required for this account.'),
            ]);
        }

        $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

        if (! $this->twoFactor->verify($secret, $code)) {
            throw ValidationException::withMessages([
                'two_factor_code' => __('The provided two factor authentication code was invalid.'),
            ]);
        }
    }

    private function expiryFor(User $user): Carbon
    {
        // The enterprise policy: 1 hour for admin surfaces, 24 hours otherwise.
        return now()->addHours($user->hasAnyRole(['SUPER_ADMIN', 'ADMIN']) ? 1 : 24);
    }

    private function auditFailure(string $email): void
    {
        try {
            $this->audit->log('auth.api.failed', null, [
                'email' => $email,
                ...AuthRequestContext::properties(),
            ], null, 'auth');
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param array<string, scalar|null> $properties
     */
    private function audit(string $event, User $user, array $properties = []): void
    {
        try {
            $this->audit->log($event, $user, [
                ...$properties,
                ...AuthRequestContext::properties(),
            ], $user, 'auth');
        } catch (Throwable $exception) {
            // Audit failure must not turn a successful login into a 500.
            report($exception);
        }
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
