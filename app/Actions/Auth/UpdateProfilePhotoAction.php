<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Shared\Domain\Ports\StoragePort;
use Throwable;

/**
 * Store one profile photo through the Shared StoragePort (R2 today).
 *
 * The upload is decoded and re-encoded before it reaches R2: that strips
 * EXIF/GPS/metadata and rejects a polyglot that is an image plus a payload.
 * Storage happens BEFORE the single DB update; if the update fails the new
 * object is removed, and a failed old-object cleanup is reported but never
 * rolls back a save that already points at the new photo.
 */
final readonly class UpdateProfilePhotoAction
{
    private const int SIZE = 320;

    public function __construct(
        private StoragePort $storage,
    ) {}

    public function store(User $user, UploadedFile $file): string
    {
        $encoded = ImageManager::usingDriver(GdDriver::class)
            ->decodeSplFileInfo($file)
            ->cover(self::SIZE, self::SIZE)
            ->encodeUsingFormat(Format::WEBP, quality: 85, strip: true);

        $key = sprintf(
            'profile-photos/%s/%s.webp',
            (string) $user->uuid,
            Str::lower((string) Str::ulid()),
        );

        $stored = $this->storage->put($key, $encoded->toString(), 'public');
        $old = $user->profile_photo_path;

        try {
            $user->forceFill(['profile_photo_path' => $stored])->save();
        } catch (Throwable $exception) {
            $this->deleteQuietly($stored);

            throw $exception;
        }

        if ($old !== null) {
            $this->deleteQuietly($old);
        }

        return $stored;
    }

    public function remove(User $user): void
    {
        $old = $user->profile_photo_path;

        $user->forceFill(['profile_photo_path' => null])->save();

        if ($old !== null) {
            $this->deleteQuietly($old);
        }
    }

    private function deleteQuietly(?string $key): void
    {
        if ($key === null || $key === '') {
            return;
        }

        try {
            $this->storage->delete($key);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
