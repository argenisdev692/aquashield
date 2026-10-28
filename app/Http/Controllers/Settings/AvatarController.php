<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Auth\UpdateAvatarAction;
use App\Data\Auth\AvatarData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateAvatarRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Shared\Domain\Ports\StoragePort;
use Throwable;

final class AvatarController extends Controller
{
    public function __construct(
        private readonly UpdateAvatarAction $updateAvatar,
        private readonly StoragePort $storage,
    ) {}

    public function store(UpdateAvatarRequest $request): AvatarData|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $file = $request->file('avatar');

        if (! $file instanceof UploadedFile) {
            abort(422);
        }

        $key = $this->updateAvatar->store($user, $file);

        if ($request->expectsJson()) {
            return new AvatarData($this->publicUrl($key));
        }

        return back()->with('status', 'avatar-updated');
    }

    public function destroy(Request $request): AvatarData|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $this->updateAvatar->remove($user);

        if ($request->expectsJson()) {
            return new AvatarData(null);
        }

        return back()->with('status', 'avatar-removed');
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
