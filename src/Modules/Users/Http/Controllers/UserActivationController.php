<?php

declare(strict_types=1);

namespace Modules\Users\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response as InertiaResponse;
use Modules\Users\Actions\ActivateUserAction;
use Modules\Users\Data\ActivationUserData;
use Modules\Users\Http\Requests\ActivateUserRequest;
use Modules\Users\Support\InvitationLink;

/**
 * The public half of the invitation flow.
 *
 * The signed URL is validated manually rather than through the `signed`
 * middleware so an expired or superseded link can render a helpful page
 * ("ask an administrator for a new invitation") instead of Laravel's generic
 * 403 error screen. The GET never mutates; only the POST does.
 */
final readonly class UserActivationController
{
    public function __construct(private ActivateUserAction $activate) {}

    public function show(Request $request, User $user): InertiaResponse
    {
        $state = $this->stateFor($request, $user);

        return inertia('Users/Activate', [
            // Identity is only disclosed once the signature, the single-use
            // marker and the lifecycle have all been verified. An unsigned or
            // stale link renders the state/message, never the account's PII.
            'user' => $state['state'] === 'ready'
                ? new ActivationUserData(
                    name: (string) $user->name,
                    email: (string) $user->email,
                )
                : null,
            'state' => $state['state'],
            'message' => $state['message'],
            'actionUrl' => $state['state'] === 'ready' ? $request->fullUrl() : null,
            'expiresInHours' => InvitationLink::EXPIRES_IN_HOURS,
        ]);
    }

    public function store(
        ActivateUserRequest $request,
        User $user,
    ): RedirectResponse {
        $state = $this->stateFor($request, $user);

        if ($state['state'] !== 'ready') {
            abort(403, $state['message']);
        }

        $invitationTimestamp = (int) $request->query('invitation', 0);

        $user = $this->activate->execute(
            $user,
            (string) $request->validated('password'),
            $invitationTimestamp,
        );

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard')->with(
            'status',
            'Your account is active. Welcome to '.config('app.name').'.',
        );
    }

    /**
     * @return array{state: 'ready'|'expired'|'invalid'|'used'|'superseded', message: string}
     */
    private function stateFor(Request $request, User $user): array
    {
        if (! $request->hasValidSignature()) {
            $expires = (int) $request->query('expires', 0);

            return $expires > 0 && $expires < now()->getTimestamp()
                ? [
                    'state' => 'expired',
                    'message' => 'This invitation link has expired. Ask an administrator to send a new one.',
                ]
                : [
                    'state' => 'invalid',
                    'message' => 'This invitation link is not valid. Check that you copied the whole address from the email.',
                ];
        }

        if (! $user->isInvited()) {
            return [
                'state' => 'used',
                'message' => 'This invitation has already been used. Sign in with your password instead.',
            ];
        }

        $timestamp = (int) $request->query('invitation', 0);

        if ($user->invitation_sent_at === null
            || $user->invitation_sent_at->getTimestamp() !== $timestamp) {
            return [
                'state' => 'superseded',
                'message' => 'A newer invitation was sent. Use the most recent email, or ask an administrator to resend it.',
            ];
        }

        return [
            'state' => 'ready',
            'message' => 'Choose a password to activate your account.',
        ];
    }
}
