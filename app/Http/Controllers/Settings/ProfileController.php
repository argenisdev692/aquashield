<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Data\Auth\PasskeyData;
use App\Data\Auth\UserData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Response;
use Shared\Domain\Ports\StoragePort;

/**
 * The account screen for the signed-in user.
 *
 * There is no update method here: Fortify owns profile, password and 2FA
 * writes (`PUT /user/profile-information`, `PUT /user/password`, the
 * `/user/two-factor-*` routes). Re-implementing those would create a second
 * source of truth for the same rules. This controller only renders the page
 * and allowlists the user fields the shell needs.
 */
final class ProfileController extends Controller
{
    public function __construct(
        private readonly StoragePort $storage,
    ) {}

    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $status = $request->session()->get('status');

        $passkeys = $user->passkeys()->latest()->get();

        return inertia('Settings/Profile', [
            'user' => UserData::fromModel($user, $this->storage)->toArray(),
            // Browser key, restricted by HTTP referrer in Google Cloud. It is
            // public by design; the server never needs it for Places calls.
            'googleMapsApiKey' => (string) config('services.google.maps_key', ''),
            'twoFactorEnabled' => $user->two_factor_secret !== null,
            'twoFactorConfirmed' => $user->two_factor_confirmed_at !== null,
            // The list is the page's server state: add/delete reload these two
            // props instead of keeping a second copy in a client cache.
            'passkeys' => PasskeyData::collect($passkeys)->toArray(),
            'passkeysCount' => $passkeys->count(),
            'status' => is_string($status) ? $status : null,
        ]);
    }
}
