<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\LeadAppointment\Application\Ports\AppointmentRepository;
use Modules\LeadAppointment\Application\Ports\AvailabilityRepository;
use Modules\LeadAppointment\Application\Ports\CalendarSyncPort;
use Modules\LeadAppointment\Application\Ports\LeadRepository;
use Modules\LeadAppointment\Application\Ports\LeadSourceGateway;
use Modules\LeadAppointment\Domain\Events\AppointmentCancelled;
use Modules\LeadAppointment\Domain\Events\AppointmentRescheduled;
use Modules\LeadAppointment\Domain\Events\AppointmentScheduled;
use Modules\LeadAppointment\Domain\Events\LeadCaptured;
use Modules\LeadAppointment\Domain\Ports\AvailabilityScheduleReader;
use Modules\LeadAppointment\Domain\Ports\HolidayProvider;
use Modules\LeadAppointment\Domain\Services\EmailSpamGuard;
use Modules\LeadAppointment\Domain\Services\SpamGuard;
use Modules\LeadAppointment\Infrastructure\Console\Commands\SyncUsHolidaysCommand;
use Modules\LeadAppointment\Infrastructure\GoogleCalendar\SpatieCalendarSyncAdapter;
use Modules\LeadAppointment\Infrastructure\Holidays\UsFederalHolidayProvider;
use Modules\LeadAppointment\Infrastructure\Listeners\NotifyOfficeOfCancelledAppointment;
use Modules\LeadAppointment\Infrastructure\Listeners\NotifyOfficeOfNewLead;
use Modules\LeadAppointment\Infrastructure\Listeners\NotifyOfficeOfRescheduledAppointment;
use Modules\LeadAppointment\Infrastructure\Listeners\NotifyOfficeOfScheduledAppointment;
use Modules\LeadAppointment\Infrastructure\Listeners\SendAppointmentCancelledEmail;
use Modules\LeadAppointment\Infrastructure\Listeners\SendAppointmentConfirmation;
use Modules\LeadAppointment\Infrastructure\Listeners\SendAppointmentRescheduled;
use Modules\LeadAppointment\Infrastructure\Listeners\SendLeadReceivedEmail;
use Modules\LeadAppointment\Infrastructure\Listeners\SyncAppointmentToGoogleCalendar;
use Modules\LeadAppointment\Infrastructure\Meta\MetaGraphLeadAdapter;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Repositories\EloquentAppointmentRepository;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Repositories\EloquentAvailabilityRepository;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Repositories\EloquentLeadRepository;

final class LeadAppointmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LeadRepository::class, EloquentLeadRepository::class);
        $this->app->bind(AppointmentRepository::class, EloquentAppointmentRepository::class);
        $this->app->bind(AvailabilityRepository::class, EloquentAvailabilityRepository::class);
        $this->app->bind(AvailabilityScheduleReader::class, EloquentAvailabilityRepository::class);
        $this->app->bind(CalendarSyncPort::class, SpatieCalendarSyncAdapter::class);
        $this->app->bind(HolidayProvider::class, UsFederalHolidayProvider::class);
        $this->app->bind(LeadSourceGateway::class, MetaGraphLeadAdapter::class);

        $this->app->singleton(SpamGuard::class, static function (): SpamGuard {
            /** @var array<string, int> $weights */
            $weights = config('lead-appointment.spam.keywords', []);

            return new SpamGuard(
                keywordWeights: $weights,
                threshold: (int) config('lead-appointment.spam.threshold', 5),
                maxLinks: (int) config('lead-appointment.spam.max_links', 1),
            );
        });

        $this->app->singleton(EmailSpamGuard::class, static function (): EmailSpamGuard {
            /** @var list<string> $blocked */
            $blocked = config('lead-appointment.spam.blocked_email_domains', []);
            /** @var list<string> $disposable */
            $disposable = config('lead-appointment.spam.disposable_email_domains', []);
            /** @var list<string> $roles */
            $roles = config('lead-appointment.spam.role_email_prefixes', []);

            return new EmailSpamGuard(
                blockedDomains: $blocked,
                disposableDomains: $disposable,
                rolePrefixes: $roles,
                threshold: (int) config('lead-appointment.spam.threshold', 5),
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api_v1.php');
        $this->loadMigrationsFrom(__DIR__.'/../Infrastructure/Persistence/Migrations');
        $this->commands([SyncUsHolidaysCommand::class]);

        RateLimiter::for('public-leads', static fn (Request $request): array => [
            Limit::perMinute((int) config('lead-appointment.public.throttle_per_minute', 5))->by($request->ip()),
            Limit::perDay((int) config('lead-appointment.public.throttle_per_day', 100))->by($request->ip()),
        ]);

        Event::listen(LeadCaptured::class, SendLeadReceivedEmail::class);
        Event::listen(LeadCaptured::class, NotifyOfficeOfNewLead::class);
        Event::listen(AppointmentScheduled::class, SendAppointmentConfirmation::class);
        Event::listen(AppointmentScheduled::class, NotifyOfficeOfScheduledAppointment::class);
        Event::listen(AppointmentScheduled::class, SyncAppointmentToGoogleCalendar::class);
        Event::listen(AppointmentRescheduled::class, SendAppointmentRescheduled::class);
        Event::listen(AppointmentRescheduled::class, NotifyOfficeOfRescheduledAppointment::class);
        Event::listen(AppointmentRescheduled::class, SyncAppointmentToGoogleCalendar::class);
        Event::listen(AppointmentCancelled::class, SendAppointmentCancelledEmail::class);
        Event::listen(AppointmentCancelled::class, NotifyOfficeOfCancelledAppointment::class);
        Event::listen(AppointmentCancelled::class, SyncAppointmentToGoogleCalendar::class);
    }
}
