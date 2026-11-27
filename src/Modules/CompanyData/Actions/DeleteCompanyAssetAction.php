<?php

declare(strict_types=1);

namespace Modules\CompanyData\Actions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\CompanyData\Enums\CompanyAsset;
use Modules\CompanyData\Models\CompanyData;
use Modules\CompanyData\Support\R2CompanyAssetStorage;

/**
 * Clears one brand asset. Logos fall back to the bundled artwork; the signature
 * becomes "none on file". The object is deleted after the row stops pointing at
 * it, never before.
 */
final class DeleteCompanyAssetAction
{
    public function __construct(
        private readonly R2CompanyAssetStorage $storage,
    ) {}

    public function execute(CompanyData $company, CompanyAsset $asset): CompanyData
    {
        /** @var array{CompanyData, string|null} $result */
        $result = DB::transaction(static function () use ($asset): array {
            $locked = CompanyData::query()
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw (new ModelNotFoundException)->setModel(CompanyData::class);
            }

            $previous = $locked->getAttribute($asset->column());
            $locked->setAttribute($asset->column(), null);
            $locked->save();

            return [$locked, is_string($previous) ? $previous : null];
        });

        [$updated, $previous] = $result;

        $this->storage->forget($previous);

        return $updated;
    }
}
