<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Dtos;

use Modules\Backups\Data\BackupArchiveData;

final readonly class BackupPage
{
    /**
     * @param list<BackupArchiveData> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public int $lastPage,
    ) {}
}
