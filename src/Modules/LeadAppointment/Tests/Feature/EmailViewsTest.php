<?php

declare(strict_types=1);

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\LeadData;
use Modules\LeadAppointment\Infrastructure\Mail\AppointmentOfficeEvent;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function emailLead(): LeadData
{
    return new LeadData(
        uuid: '0199a000-0000-7000-8000-000000000001',
        firstName: 'Ada',
        lastName: 'Lovelace',
        fullName: 'Ada Lovelace',
        companyName: null,
        email: 'ada@example.com',
        phone: '+17135550199',
        address: '123 Main St',
        address2: null,
        city: 'Houston',
        state: 'TX',
        zipCode: '77002',
        addressLine: '123 Main St, Houston, TX, 77002',
        region: 'houston',
        source: 'website',
        sourceDetail: 'Website form',
        status: 'new',
        ownerUuid: null,
        ownerName: null,
        duplicateOfUuid: null,
        isEmergency: false,
        isSpam: false,
        spamScore: 0,
        spamReasons: [],
        preferredLanguage: 'en',
        damageDescription: 'Water stain on the ceiling.',
        insuranceCarrier: null,
        claimNumber: null,
        smsConsent: false,
        smsConsentAt: null,
        lastContactedAt: null,
        convertedAt: null,
        notes: null,
        attribution: null,
        createdAt: '2026-11-19T12:00:00+00:00',
        updatedAt: '2026-11-19T12:00:00+00:00',
        deletedAt: null,
    );
}

function emailAppointment(): AppointmentData
{
    return new AppointmentData(
        uuid: '0199a000-0000-7000-8000-000000000002',
        leadUuid: '0199a000-0000-7000-8000-000000000001',
        customerName: 'Ada Lovelace',
        customerEmail: 'ada@example.com',
        customerPhone: '+17135550199',
        addressLine: '123 Main St, Houston, TX, 77002',
        kind: 'inspection',
        status: 'scheduled',
        startsAt: '2026-12-07T16:00:00+00:00',
        endsAt: '2026-12-07T17:00:00+00:00',
        startsAtLocal: '2026-12-07T10:00:00-06:00',
        endsAtLocal: '2026-12-07T11:00:00-06:00',
        timezone: 'America/Chicago',
        region: 'houston',
        previousStartsAt: null,
        googleEventId: null,
        notes: 'Gate code 1234.',
        cancelReason: null,
        createdAt: '2026-11-19T12:00:00+00:00',
        deletedAt: null,
    );
}

it('renders the lead-received email with the corporate layout', function (): void {
    $html = view('emails.lead-appointment.lead-received', ['lead' => emailLead()])->render();

    expect($html)
        ->toContain('We received your request')
        ->toContain('Ada')
        ->toContain('roof inspection')
        ->toContain('#00b5e2');
});

it('renders the appointment confirmation email with the visit details', function (): void {
    $html = view('emails.lead-appointment.appointment-confirmed', [
        'appointment' => emailAppointment(),
        'reason' => 'Rescheduled by staff',
    ])->render();

    expect($html)
        ->toContain('Your appointment is scheduled')
        ->toContain('Ada Lovelace')
        ->toContain('inspection')
        ->toContain('December 7, 2026');
});

it('renders the office email for a scheduled appointment', function (): void {
    $html = view('emails.lead-appointment.appointment-office', [
        'appointment' => emailAppointment(),
        'event' => AppointmentOfficeEvent::Scheduled,
        'reason' => null,
    ])->render();

    expect($html)
        ->toContain('New appointment scheduled')
        ->toContain('Ada Lovelace')
        ->toContain('Open the appointment')
        ->not->toContain('Previous time');
});

it('renders the office email with the previous time and reason after a reschedule', function (): void {
    $appointment = emailAppointment();
    $appointment->previousStartsAt = '2026-12-01T16:00:00+00:00';

    $html = view('emails.lead-appointment.appointment-office', [
        'appointment' => $appointment,
        'event' => AppointmentOfficeEvent::Rescheduled,
        'reason' => 'Customer asked for the afternoon',
    ])->render();

    expect($html)
        ->toContain('Appointment rescheduled')
        ->toContain('Previous time')
        ->toContain('Customer asked for the afternoon');
});

it('renders the office email with the cancellation reason', function (): void {
    $appointment = emailAppointment();
    $appointment->status = 'cancelled';

    $html = view('emails.lead-appointment.appointment-office', [
        'appointment' => $appointment,
        'event' => AppointmentOfficeEvent::Cancelled,
        'reason' => 'Weather',
    ])->render();

    expect($html)
        ->toContain('Appointment cancelled')
        ->toContain('Weather');
});

it('renders the appointment PDF from the branded exports.pdf.layout', function (): void {
    $pdf = Pdf::loadView('exports.lead-appointment.appointment', [
        'appointment' => emailAppointment(),
    ])->output();

    expect($pdf)->toStartWith('%PDF');
});

it('renders the tabular PDF layout with the brand palette', function (): void {
    $html = view('exports.lead-appointment.leads', [
        'title' => 'Leads export',
        'columns' => ['name' => 'Name', 'email' => 'Email'],
        'rows' => [['name' => 'Ada Lovelace', 'email' => 'ada@example.com']],
    ])->render();

    expect($html)
        ->toContain('Leads export')
        ->toContain('Ada Lovelace')
        ->toContain('#0c2340');
});
