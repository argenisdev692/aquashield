<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\CompanyData\Models\CompanyData;
use Shared\Infrastructure\Company\CompanyProfile;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('encrypts financial fields at rest and decrypts them for reads and PDFs', function (): void {
    $owner = User::factory()->create();

    $company = new CompanyData;
    $company->fill([
        'company_name' => 'AquaShield Restoration USA',
        'email' => 'info@aquashieldrestorationusa.com',
        'bank_iban' => 'PT50 SECRET',
        'nif_nipc' => '123456789',
        'invoice_notes' => 'Sensitive billing note',
    ]);
    $company->user_id = $owner->id;
    $company->save();

    $raw = DB::table('company_data')->where('id', $company->id)->first();

    expect($raw->bank_iban)->not->toBe('PT50 SECRET')
        ->and($raw->bank_iban)->not->toContain('PT50 SECRET')
        ->and($raw->nif_nipc)->not->toBe('123456789')
        ->and($raw->invoice_notes)->not->toContain('Sensitive billing note')
        ->and($company->fresh()?->bank_iban)->toBe('PT50 SECRET')
        ->and(CompanyProfile::pdfBranding()['bank_iban'])->toBe('PT50 SECRET')
        ->and(CompanyProfile::pdfBranding()['nif_nipc'])->toBe('123456789')
        ->and(CompanyProfile::pdfBranding()['invoice_notes'])->toBe('Sensitive billing note');
});
