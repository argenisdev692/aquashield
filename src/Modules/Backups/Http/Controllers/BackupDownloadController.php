<?php

declare(strict_types=1);

namespace Modules\Backups\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Backups\Application\Repositories\BackupArchiveStore;
use Shared\Domain\Ports\AuditPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class BackupDownloadController
{
    public function __construct(
        private BackupArchiveStore $archives,
        private AuditPort $audit,
    ) {}

    public function __invoke(Request $request, string $backup): StreamedResponse
    {
        $archive = $this->archives->findActive($backup);

        if ($archive === null) {
            abort(404);
        }

        $stream = $this->archives->stream($archive);

        $this->audit->log('backup.downloaded', null, [
            'disk' => $archive->disk,
            'filename' => $archive->filename,
            'size_bytes' => $archive->sizeBytes,
        ], $request->user(), 'backups');

        return response()->streamDownload(function () use ($stream): void {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $archive->filename, ['Content-Type' => 'application/zip']);
    }
}
