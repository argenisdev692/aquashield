/**
 * Auth/VerifyEmail — the verification prompt for a signed-in, unverified user.
 *
 * Fortify's `verification.notice` route (`GET /email/verify`) renders this when
 * `hasVerifiedEmail()` is false, and `POST /email/verification-notification`
 * re-sends the signed link. The page was a placeholder whose "Resend email"
 * button had no form behind it, so an unverified account could neither resend
 * nor leave — every `verified` route dead-ended here.
 *
 * There is no "back to sign in" link on purpose: the visitor is already
 * authenticated, so `/login` would bounce them straight back. Signing out is
 * the real escape hatch when the account is the wrong one.
 */
import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useLogout } from '@/lib/auth';
import AuthLayout from '@/Layouts/AuthLayout';
import AppButton from '@/Components/Forms/AppButton';

interface VerifyEmailPageProps {
    /** Inertia's PageProps requires an index signature. */
    [key: string]: unknown;
    /** Fortify flashes `verification-link-sent` after a resend. */
    status: string | null;
    /** The signed-in address, passed by the view closure. */
    email: string | null;
}

export default function VerifyEmail() {
    const { status, email } = usePage<VerifyEmailPageProps>().props;
    const [resending, setResending] = useState(false);
    const logout = useLogout();

    const resend = (): void => {
        router.post(
            '/email/verification-notification',
            {},
            {
                preserveScroll: true,
                onStart: () => setResending(true),
                onFinish: () => setResending(false),
            },
        );
    };

    return (
        <AuthLayout
            title="Verify your email"
            subtitle="One more step before you continue"
            width="md"
        >
            <Head title="Verify your email" />

            {status === 'verification-link-sent' ? (
                <p
                    role="status"
                    className="mb-4 rounded-md border border-aq-accent/40 bg-aq-accent/10 px-3 py-2.5 text-sm font-medium text-aq-ink"
                >
                    A new verification link is on its way.
                </p>
            ) : null}

            <p className="text-sm leading-relaxed text-aq-ink-muted">
                We sent a verification link to{' '}
                <span className="font-semibold text-aq-ink">{email ?? 'your inbox'}</span>. Open it
                to activate your account, then come back to this tab.
            </p>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    resend();
                }}
                className="mt-5"
            >
                <AppButton type="submit" shine loading={resending} className="h-11 w-full">
                    Resend verification email
                </AppButton>
            </form>

            <div className="mt-5 border-t border-aq-line pt-4 text-center">
                <button
                    type="button"
                    onClick={logout}
                    className="rounded-sm text-sm text-aq-ink-muted transition-colors hover:text-aq-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
                >
                    Wrong account? Sign out
                </button>
            </div>
        </AuthLayout>
    );
}
