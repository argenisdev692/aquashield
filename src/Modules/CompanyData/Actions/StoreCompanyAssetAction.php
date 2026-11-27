<?php

declare(strict_types=1);

namespace Modules\CompanyData\Actions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\CompanyData\Enums\CompanyAsset;
use Modules\CompanyData\Models\CompanyData;
use Modules\CompanyData\Support\R2CompanyAssetStorage;
use SplFileInfo;
use Throwable;

/**
 * Replaces one brand asset: the new object is written first, the row is pointed
 * at its key, and only then is the previous object deleted. A failure at any
 * earlier step leaves the profile rendering the asset it already had.
 */
final class StoreCompanyAssetAction
{
    public function __construct(
        private readonly R2CompanyAssetStorage $storage,
    ) {}

    public function execute(CompanyData $company, CompanyAsset $asset, SplFileInfo $file): CompanyData
    {
        $key = $this->storage->store($asset, (string) $company->uuid, $file);

        try {
            /** @var array{CompanyData, string|null} $result */
            $result = DB::transaction(static function () use ($asset, $key): array {
                // Re-read under a lock so concurrent replacements and deletes
                // derive cleanup from the current committed singleton.
                $locked = CompanyData::query()
                    ->oldest('id')
                    ->lockForUpdate()
                    ->first();

                if ($locked === null) {
                    throw (new ModelNotFoundException)->setModel(CompanyData::class);
                }

                $previous = $locked->getAttribute($asset->column());

                // Assignment rather than fill(): the asset columns stay out of
                // the mass-assignment allowlist on purpose, so a profile PUT
                // can never point them at an attacker-chosen key.
                $locked->setAttribute($asset->column(), $key);
                $locked->save();

                return [$locked, is_string($previous) ? $previous : null];
            });
        } catch (Throwable $exception) {
            // The row never pointed at this object. Best-effort compensation
            // prevents a failed save from leaving an unreferenced upload.
            $this->storage->forget($key);

            throw $exception;
        }

        [$updated, $previous] = $result;

        $this->storage->forget($previous);

        return $updated;
    }
}
