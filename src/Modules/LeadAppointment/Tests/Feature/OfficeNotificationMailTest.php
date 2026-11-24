<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\CompanyData\Models\CompanyData;
use Modules\LeadAppointment\Infrastructure\Mail\AppointmentOfficeEvent;
use Modules\LeadAppointment\Infrastructure\Mail\AppointmentOfficeMail;
use Modules\LeadAppointment\Infrastructure\Mail\NewLeadMail;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
    $this->start = LeadAppointmentFixtures::nextWeekdayAt('10:00');

    foreach ([1, 2, 3, 4, 5] as $dayOfWeek) {
        LeadAppointmentFixtures::companyRule($dayOfWeek, '08:00', '17:00');
    }

    $company = new CompanyData;
    $company->fill([
        'name' => 'Victor Lara',
        'company_name' => 'AquaShield Restoration USA',
        'email' => 'office@aquashieldrestorationusa.com',
    ]);
    $company->user_id = $this->admin->id;
    $company->save();

    Role::findOrCreate('ADMIN', 'web');
});

function officeNotificationAdmin(string $email): User
{
    $user = User::factory()->create(['email' => $email]);
    $user->assignRole('ADMIN');

    return $user;
}

/**
 * @return array<string, mixed>
 */
function officeNotificationAppointmentPayload(string $leadUuid, string $startsAt): array
{
    return [
        'lead_uuid' => $leadUuid,
        'kind' => 'inspection',
        'starts_at' => $startsAt,
        'duration_minutes' => 60,
    ];
}

it('emails the company mailbox and cc the admin role when a lead is first captured', function (): void {
    officeNotificationAdmin('admin@aquashieldrestorationusa.com');

    $this->postJson('/api/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@aquashieldrestorationusa.com',
        'phone' => '(713) 555-0199',
        'address' => '123 Main St',
        'city' => 'Houston',
        'state' => 'TX',
        'zip_code' => '77002',
        'region' => 'houston',
        'damage_description' => 'Water stain on the ceiling.',
    ])->assertCreated();

    Mail::assertQueued(NewLeadMail::class, function (NewLeadMail $mail): bool {
        return $mail->hasTo('office@aquashieldrestorationusa.com')
            && $mail->hasCc('admin@aquashieldrestorationusa.com');
    });
});

it('cc every admin role mailbox and never duplicates the company address', function (): void {
    officeNotificationAdmin('admin@aquashieldrestorationusa.com');
    officeNotificationAdmin('supervisor@aquashieldrestorationusa.com');
    officeNotificationAdmin('office@aquashieldrestorationusa.com');

    $this->postJson('/api/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@aquashieldrestorationusa.com',
        'phone' => '(713) 555-0199',
        'region' => 'houston',
    ])->assertCreated();

    Mail::assertQueued(NewLeadMail::class, function (NewLeadMail $mail): bool {
        return $mail->hasTo('office@aquashieldrestorationusa.com')
            && $mail->hasCc('admin@aquashieldrestorationusa.com')
            && $mail->hasCc('supervisor@aquashieldrestorationusa.com')
            && ! $mail->hasCc('office@aquashieldrestorationusa.com');
    });
});

it('sends the office copy to the company mailbox without a cc when no admin role user exists', function (): void {
    $this->postJson('/api/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@aquashieldrestorationusa.com',
        'phone' => '(713) 555-0199',
        'region' => 'houston',
    ])->assertCreated();

    Mail::assertQueued(NewLeadMail::class, function (NewLeadMail $mail): bool {
        return $mail->hasTo('office@aquashieldrestorationusa.com')
            && $mail->cc === [];
    });
});

it('notifies the office when an appointment is scheduled', function (): void {
    officeNotificationAdmin('admin@aquashieldrestorationusa.com');
    $lead = LeadAppointmentFixtures::lead();

    $this->actingAs($this->admin)
        ->postJson('/appointments', officeNotificationAppointmentPayload(
            $lead->uuid,
            $this->start->format('Y-m-d H:i'),
        ))
        ->assertCreated();

    Mail::assertQueued(AppointmentOfficeMail::class, function (AppointmentOfficeMail $mail): bool {
        return $mail->event === AppointmentOfficeEvent::Scheduled
            && $mail->hasTo('office@aquashieldrestorationusa.com')
            && $mail->hasCc('admin@aquashieldrestorationusa.com');
    });
});

it('notifies the office when an appointment is rescheduled', function (): void {
    officeNotificationAdmin('admin@aquashieldrestorationusa.com');
    $lead = LeadAppointmentFixtures::lead();

    $created = $this->actingAs($this->admin)
        ->postJson('/appointments', officeNotificationAppointmentPayload(
            $lead->uuid,
            $this->start->format('Y-m-d H:i'),
        ))
        ->assertCreated();

    $this->actingAs($this->admin)
        ->postJson('/appointments/'.$created->json('uuid').'/reschedule', [
            'starts_at' => $this->start->addDay()->setTime(14, 0)->format('Y-m-d H:i'),
            'duration_minutes' => 60,
            'reason' => 'Customer asked for the afternoon',
        ])
        ->assertOk();

    Mail::assertQueued(AppointmentOfficeMail::class, function (AppointmentOfficeMail $mail): bool {
        return $mail->event === AppointmentOfficeEvent::Rescheduled
            && $mail->hasTo('office@aquashieldrestorationusa.com')
            && $mail->hasCc('admin@aquashieldrestorationusa.com')
            && $mail->reason === 'Customer asked for the afternoon';
    });
});

it('notifies the office when an appointment is cancelled', function (): void {
    officeNotificationAdmin('admin@aquashieldrestorationusa.com');
    $lead = LeadAppointmentFixtures::lead();

    $created = $this->actingAs($this->admin)
        ->postJson('/appointments', officeNotificationAppointmentPayload(
            $lead->uuid,
            $this->start->format('Y-m-d H:i'),
        ))
        ->assertCreated();

    $this->actingAs($this->admin)
        ->postJson('/appointments/'.$created->json('uuid').'/cancel', ['reason' => 'Weather'])
        ->assertOk();

    Mail::assertQueued(AppointmentOfficeMail::class, function (AppointmentOfficeMail $mail): bool {
        return $mail->event === AppointmentOfficeEvent::Cancelled
            && $mail->hasTo('office@aquashieldrestorationusa.com')
            && $mail->hasCc('admin@aquashieldrestorationusa.com')
            && $mail->reason === 'Weather';
    });
});
