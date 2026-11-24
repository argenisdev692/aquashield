/**
 * Welcome page — landing screen with brand components.
 * React port of the Vue Welcome page: the kinetic hero, the floating Why bar,
 * the product band, the brand moment, the workspace paths and the footer.
 */
import { Head, usePage } from '@inertiajs/react';
import WelcomeBrandMoment from '@/Components/WelcomeBrandMoment';
import WelcomeHeader from '@/Components/WelcomeHeader';
import WelcomeHero from '@/Components/WelcomeHero';
import WelcomeMockup from '@/Components/WelcomeMockup';
import WelcomePaths from '@/Components/WelcomePaths';
import WelcomeWhy from '@/Components/WelcomeWhy';
import WelcomeFooter from '@/Components/WelcomeFooter';
import CookieConsentBanner from '@/Components/CookieConsentBanner';
import CursorOrb from '@/Components/CursorOrb';

type WelcomePageProps = {
    company?: Modules.CompanyData.Data.CompanyBrandingDto;
};

export default function Welcome() {
    const page = usePage<WelcomePageProps>();
    const company = page.props.company;

    return (
        <div className="min-h-screen bg-aq-void">
            <Head title={`${company?.name ?? 'AquaShield Restoration USA'} Operations`} />

            <WelcomeHeader company={company} />

            <main id="main">
                <WelcomeHero />
                <WelcomeWhy />
                <WelcomeMockup />
                <WelcomeBrandMoment />
                <WelcomePaths />
            </main>

            <WelcomeFooter />
            <CookieConsentBanner />
            <CursorOrb />
        </div>
    );
}
