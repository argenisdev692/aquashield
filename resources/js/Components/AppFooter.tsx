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

/*
 * Module scope, not render: a calendar year read during render is an impure
 * call React Compiler (and the lint rule) flags. It is also the correct
 * lifetime — the value cannot change without a page load.
 */
const FOOTER_YEAR = new Date().getFullYear();

export default function AppFooter({ className, social = true }: AppFooterProps) {
    return (
        <footer className={className}>
            <div className="mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-3 px-4 py-6 text-xs text-surface-500 sm:flex-row sm:px-6 lg:px-8 dark:text-surface-300">
                <p>© {FOOTER_YEAR} AquaShield Restoration USA · All rights reserved</p>

                <div className="flex flex-wrap items-center justify-center gap-x-5 gap-y-3">
                    <nav aria-label="Legal" className="flex items-center gap-4">
                        {LEGAL_LINKS.map((link) => (
                            <a
                                key={link.label}
                                href={link.href}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label={`${link.label} (opens in a new tab)`}
                                className="rounded-md font-medium transition-colors duration-150 hover:text-surface-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none dark:hover:text-surface-200"
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
