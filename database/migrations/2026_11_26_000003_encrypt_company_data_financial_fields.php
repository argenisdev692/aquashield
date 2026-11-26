<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypts the company's financial/tax fields at rest.
 *
 * The `encrypted` Eloquent casts (CompanyData) produce ciphertext longer than
 * the original string columns, so the columns are widened to text first. The
 * existing rows are encrypted in place with the same crypt contract the cast
 * uses, which is also what CompanyProfile::pdfBranding() decrypts for PDFs.
 *
 * Rotating APP_KEY after this migration requires a deliberate re-encryption
 * pass; the values are no longer readable with the old key.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const array COLUMNS = [
        'nif_nipc',
        'nie',
        'bank_beneficiary',
        'bank_iban',
        'bank_bic',
        'bank_name',
        'invoice_notes',
    ];

    public function up(): void
    {
        // SQLite has TEXT affinity and does not enforce string lengths; skip
        // the rebuild so the singleton CHECK triggers from the previous
        // migration are not dropped by a table copy.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('company_data', function (Blueprint $table): void {
                foreach (self::COLUMNS as $column) {
                    $table->text($column)->nullable()->change();
                }
            });
        }

        DB::transaction(function (): void {
            DB::table('company_data')->orderBy('id')->get()->each(function (object $row): void {
                $updates = [];

                foreach (self::COLUMNS as $column) {
                    $value = $row->{$column} ?? null;

                    if (! is_string($value) || $value === '') {
                        continue;
                    }

                    try {
                        Crypt::decryptString($value);

                        continue;
                    } catch (Throwable) {
                        // Plaintext row from before the cast existed.
                        $updates[$column] = Crypt::encryptString($value);
                    }
                }

                if ($updates !== []) {
                    DB::table('company_data')->where('id', $row->id)->update($updates);
                }
            });
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('company_data')->orderBy('id')->get()->each(function (object $row): void {
                $updates = [];

                foreach (self::COLUMNS as $column) {
                    $value = $row->{$column} ?? null;

                    if (! is_string($value) || $value === '') {
                        continue;
                    }

                    try {
                        $updates[$column] = Crypt::decryptString($value);
                    } catch (Throwable) {
                        // Already plaintext.
                    }
                }

                if ($updates !== []) {
                    DB::table('company_data')->where('id', $row->id)->update($updates);
                }
            });
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('company_data', function (Blueprint $table): void {
                $table->string('nif_nipc', 20)->nullable()->change();
                $table->string('nie', 20)->nullable()->change();
                $table->string('bank_beneficiary', 255)->nullable()->change();
                $table->string('bank_iban', 34)->nullable()->change();
                $table->string('bank_bic', 11)->nullable()->change();
                $table->string('bank_name', 255)->nullable()->change();
                $table->text('invoice_notes')->nullable()->change();
            });
        }
    }
};
