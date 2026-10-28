<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\CompanyData\Infrastructure\Persistence\CompanyData;
use Modules\CompanyData\Tests\Support\FakeStoragePort;
use Shared\Domain\Ports\StoragePort;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('VIEW_COMPANY_DATA', 'web');
    Permission::findOrCreate('UPDATE_COMPANY_DATA', 'web');

    $this->user = User::factory()->create();
    $this->storage = new FakeStoragePort;

    $this->app->instance(StoragePort::class, $this->storage);
});

function assetCompanyRecord(User $owner): CompanyData
{
    $company = new CompanyData;

    $company->fill([
        'name' => 'Victor Lara',
        'company_name' => 'AquaShield Restoration USA',
        'email' => 'info@aquashieldrestorationusa.com',
    ]);

    $company->user_id = $owner->id;
    $company->save();

    return $company;
}

/** @return array<string, string> */
function jsonHeaders(): array
{
    return ['Accept' => 'application/json'];
}

it('requires authentication for asset uploads', function (): void {
    $this->post('/company-data/assets/logo', [], jsonHeaders())->assertUnauthorized();
});

it('forbids an upload without the update permission', function (): void {
    $this->user->givePermissionTo('VIEW_COMPANY_DATA');

    $this->actingAs($this->user)
        ->post('/company-data/assets/logo', ['file' => UploadedFile::fake()->image('logo.png')], jsonHeaders())
        ->assertForbidden();
});

it('answers 404 when the singleton has not been provisioned', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');

    $this->actingAs($this->user)
        ->post('/company-data/assets/logo', ['file' => UploadedFile::fake()->image('logo.png', 100, 100)], jsonHeaders())
        ->assertNotFound();
});

it('re-encodes a logo to webp, stores it public and returns its url', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');
    $company = assetCompanyRecord($this->user);

    $response = $this->actingAs($this->user)
        ->post('/company-data/assets/logo', ['file' => UploadedFile::fake()->image('logo.png', 400, 200)], jsonHeaders())
        ->assertOk();

    $key = $company->refresh()->logo_path;

    expect($key)->toBeString()
        ->and($key)->toStartWith('company/'.$company->uuid.'/logo-')
        ->and($key)->toEndWith('.webp');

    expect($this->storage->objects)->toHaveCount(1);
    expect($this->storage->objects[$key]['visibility'])->toBe('public');
    expect($this->storage->objects[$key]['contents'])->toStartWith('RIFF');
    expect(substr($this->storage->objects[$key]['contents'], 8, 4))->toBe('WEBP');

    $response->assertJsonPath('logo_url', 'https://cdn.aquashield.test/'.$key);
});

it('keeps the signature private, as png, behind a signed url', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');
    $company = assetCompanyRecord($this->user);

    $response = $this->actingAs($this->user)
        ->post('/company-data/assets/signature', ['file' => UploadedFile::fake()->image('signature.png', 300, 120)], jsonHeaders())
        ->assertOk();

    $key = $company->refresh()->signature_path;

    expect($key)->toBeString()
        ->and($key)->toEndWith('.png');

    expect($this->storage->objects[$key]['visibility'])->toBe('private');

    $response->assertJsonPath('signature_url', 'https://cdn.aquashield.test/signed/'.$key);
});

it('deletes the previous object only after the row points at the new one', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');
    $company = assetCompanyRecord($this->user);

    $this->actingAs($this->user)
        ->post('/company-data/assets/logo', ['file' => UploadedFile::fake()->image('first.png', 200, 200)], jsonHeaders())
        ->assertOk();

    $first = (string) $company->refresh()->logo_path;

    $this->actingAs($this->user)
        ->post('/company-data/assets/logo', ['file' => UploadedFile::fake()->image('second.png', 200, 200)], jsonHeaders())
        ->assertOk();

    $second = (string) $company->refresh()->logo_path;

    expect($second)->not->toBe($first)
        ->and($this->storage->deleted)->toBe([$first])
        ->and($this->storage->exists($first))->toBeFalse();
});

it('rejects a file that is not a raster image', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');
    assetCompanyRecord($this->user);

    $this->actingAs($this->user)
        ->post(
            '/company-data/assets/logo',
            ['file' => UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf')],
            jsonHeaders(),
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('rejects a tracking-pixel sized image', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');
    assetCompanyRecord($this->user);

    $this->actingAs($this->user)
        ->post('/company-data/assets/logo', ['file' => UploadedFile::fake()->image('pixel.png', 1, 1)], jsonHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('clears an asset and deletes its object', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');
    $company = assetCompanyRecord($this->user);

    $this->actingAs($this->user)
        ->post('/company-data/assets/mark', ['file' => UploadedFile::fake()->image('mark.png', 120, 120)], jsonHeaders())
        ->assertOk();

    $key = (string) $company->refresh()->mark_path;

    $this->actingAs($this->user)
        ->delete('/company-data/assets/mark', [], jsonHeaders())
        ->assertOk()
        ->assertJsonPath('mark_url', rtrim((string) config('app.url'), '/').'/img/logo-mark.webp');

    expect($company->refresh()->mark_path)->toBeNull()
        ->and($this->storage->deleted)->toBe([$key]);
});

it('answers 404 for an unknown asset kind', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');
    assetCompanyRecord($this->user);

    $this->actingAs($this->user)
        ->post('/company-data/assets/signature_pad', ['file' => UploadedFile::fake()->image('x.png', 50, 50)], jsonHeaders())
        ->assertNotFound();
});
