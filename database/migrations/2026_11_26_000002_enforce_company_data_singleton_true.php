<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Makes the singleton a true boolean invariant on existing installs.
 *
 * The original `unique(singleton)` index allows one `true` row **and** one
 * `false` row; a legacy/raw writer could therefore keep a second profile out
 * of `CompanyData::current()`'s view. With every row constrained to `true`,
 * the existing unique index permits exactly one row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (DB::table('company_data')->where('singleton', false)->exists()) {
            throw new RuntimeException(
                'company_data contains a singleton=false row; resolve it before enforcing the singleton invariant.',
            );
        }

        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE company_data ADD CONSTRAINT company_data_singleton_true CHECK (singleton)');

            return;
        }

        if ($driver === 'sqlite') {
            // SQLite cannot add a CHECK constraint to an existing table.
            // Two triggers give the same invariant on the test engine.
            DB::statement(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS company_data_singleton_true_insert
                BEFORE INSERT ON company_data
                FOR EACH ROW WHEN NEW.singleton = 0
                BEGIN
                    SELECT RAISE(ABORT, 'company_data.singleton must be true');
                END;
            SQL);

            DB::statement(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS company_data_singleton_true_update
                BEFORE UPDATE OF singleton ON company_data
                FOR EACH ROW WHEN NEW.singleton = 0
                BEGIN
                    SELECT RAISE(ABORT, 'company_data.singleton must be true');
                END;
            SQL);

            return;
        }

        throw new RuntimeException("Unsupported database driver [{$driver}] for the company_data singleton constraint.");
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE company_data DROP CONSTRAINT company_data_singleton_true');

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS company_data_singleton_true_insert');
            DB::statement('DROP TRIGGER IF EXISTS company_data_singleton_true_update');
        }
    }
};
