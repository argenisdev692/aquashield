<?php

declare(strict_types=1);

namespace Modules\CompanyData\Actions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\CompanyData\Data\CompanyDataDto;
use Modules\CompanyData\Data\SaveCompanyDataData;
use Modules\CompanyData\Models\CompanyData;

final class SaveCompanyDataAction
{
    /** @var list<string> */
    private const array FINANCIAL_FIELDS = [
        'nif_nipc',
        'nie',
        'bank_beneficiary',
        'bank_iban',
        'bank_bic',
        'bank_name',
        'invoice_notes',
    ];

    public function execute(SaveCompanyDataData $data): CompanyDataDto
    {
        // The profile write and the activity entries are multiple inserts; the
        // transaction keeps the audit trail atomic with the change it records.
        return DB::transaction(function () use ($data): CompanyDataDto {
            // The singleton is the aggregate root: lock the row so two
            // concurrent profile saves cannot both read the same snapshot and
            // overwrite each other's financial audit.
            $company = CompanyData::query()
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if ($company === null) {
                throw (new ModelNotFoundException)->setModel(CompanyData::class);
            }

            $company->fill($data->toArray());

            $company->save();

            $this->recordFinancialChanges($company);

            return CompanyDataDto::from($company);
        });
    }

    private function recordFinancialChanges(CompanyData $company): void
    {
        $changed = array_values(array_intersect(
            array_keys($company->getChanges()),
            self::FINANCIAL_FIELDS,
        ));

        if ($changed === []) {
            return;
        }

        // Field names only: the audit proves a financial change happened
        // without copying the IBAN, tax id or bank details into the log.
        activity('company-data')
            ->performedOn($company)
            ->event('financial-fields-updated')
            ->withProperties(['changed' => implode(', ', $changed)])
            ->log('Updated sensitive company billing fields');
    }
}
