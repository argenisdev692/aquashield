/**
 * AppFooter — slim legal/copyright line shared by every app shell.
 * React port of the Vue AppFooter: one component, both shells.
 *
 * No top border: the footer is the end of the page, not a box. The gap above
 * it is the divider, and the gutters match `<main>` so the copyright text sits
 * on the same vertical line as the content it closes.
 */
import SocialLinks from '@/Components/SocialLinks';

interface AppFooterProps {
    className?: string;
    /** Show the brand's public profiles — the auth shell opts in. */
    social?: boolean;
}

interface FooterLink {
    readonly label: string;
    readonly href: string;
}

const LEGAL_LINKS: readonly FooterLink[] = [
    { label: 'Privacy Policy', href: 'https://aquashieldrestorationusa.com/privacy-policy' },
    {
        label: 'Terms & Conditions',
        href: 'https://aquashieldrestorationusa.com/terms-and-conditions',
    },
];

export default function AppFooter({ className, social = true }: AppFooterProps) {
    const year = new Date().getFullYear();

    return (
        <footer className={className}>
            <div className="mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-3 px-4 py-6 text-xs text-surface-500 sm:flex-row sm:px-6 lg:px-8 dark:text-surface-400">
                <p>© {year} AquaShield Restoration USA · All rights reserved</p>

                <div className="flex flex-wrap items-center justify-center gap-x-5 gap-y-3">
                    <nav aria-label="Legal" className="flex items-center gap-4">
                        {LEGAL_LINKS.map((link) => (
                            <a
                                key={link.label}
                                href={link.href}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label={`${link.label} (opens in a new tab)`}
                                className="rounded-md font-medium transition-colors duration-150 hover:text-surface-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none dark:hover:text-surface-200"
                            >
                                {link.label}
                            </a>
                        ))}
                    </nav>

                    {social && (
                        <>
                            <span
                                aria-hidden="true"
                                className="hidden h-4 w-px bg-surface-200 sm:block dark:bg-surface-700"
                            />
                            <SocialLinks />
                        </>
                    )}
                </div>
            </div>
        </footer>
    );
}
