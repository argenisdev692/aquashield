<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Application\Ports\LeadReader;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    LeadAppointmentFixtures::seedPermissions();
});

/*
 * An export is a read of the same rows the list shows. Holding EXPORT_* without
 * VIEW_ANY_* must never widen that list — the route carries both gates, exactly
 * like the Users export.
 */
it('refuses an export to a user who may export but not list', function (string $permission, string $uri): void {
    $exportOnly = User::factory()->create();
    $exportOnly->givePermissionTo($permission);

    $this->actingAs($exportOnly)->get($uri)->assertForbidden();
})->with([
    'leads' => ['EXPORT_LEAD', '/leads/export/csv'],
    'appointments' => ['EXPORT_APPOINTMENT', '/appointments/export/csv'],
    'lead sources report' => ['EXPORT_LEAD', '/reports/lead-sources/export/csv'],
]);

it('allows the lead export when both the read and export capabilities are held', function (): void {
    $exporter = User::factory()->create();
    $exporter->givePermissionTo(['VIEW_ANY_LEAD', 'EXPORT_LEAD']);

    $this->actingAs($exporter)
        ->get('/leads/export/csv')
        ->assertOk();

    $entry = Activity::query()->where('event', 'lead.exported')->sole();

    expect($entry->log_name)->toBe('leads')
        ->and($entry->properties->toArray())->not->toHaveKey('search');
});

it('refuses a PDF export above the PDF row cap', function (): void {
    $this->mock(LeadReader::class)
        ->shouldReceive('count')
        ->once()
        ->andReturn(2_001);

    $this->actingAs(LeadAppointmentFixtures::superAdmin())
        ->get('/leads/export/pdf')
        ->assertStatus(422);
});
