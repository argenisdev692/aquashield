<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Auth\UpdateProfilePhotoAction;
use App\Data\Auth\ProfilePhotoData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateProfilePhotoRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Shared\Domain\Ports\StoragePort;
use Throwable;

final class ProfilePhotoController extends Controller
{
    public function __construct(
        private readonly UpdateProfilePhotoAction $updatePhoto,
        private readonly StoragePort $storage,
    ) {}

    public function store(UpdateProfilePhotoRequest $request): ProfilePhotoData|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $file = $request->file('photo');

        if (! $file instanceof UploadedFile) {
            abort(422);
        }

        $key = $this->updatePhoto->store($user, $file);

        if ($request->expectsJson()) {
            return new ProfilePhotoData($this->publicUrl($key));
        }

        return back()->with('status', 'profile-photo-updated');
    }

    public function destroy(Request $request): ProfilePhotoData|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $this->updatePhoto->remove($user);

        if ($request->expectsJson()) {
            return new ProfilePhotoData(null);
        }

        return back()->with('status', 'profile-photo-removed');
    }

    private function publicUrl(string $key): ?string
    {
        try {
            return $this->storage->publicUrl($key);
        } catch (Throwable) {
            return null;
        }
    }
}
