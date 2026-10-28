<?php

declare(strict_types=1);

namespace Modules\CompanyData\Tests\Support;

use DateTimeInterface;
use LogicException;
use Shared\Domain\Ports\StoragePort;
use SplFileInfo;

/**
 * In-memory StoragePort for the asset tests.
 *
 * The upload pipeline re-encodes with GD before it ever reaches the port, so a
 * fake that keeps the bytes proves the re-encode happened without needing R2.
 */
final class FakeStoragePort implements StoragePort
{
    /** @var array<string, array{contents: string, visibility: string}> */
    public array $objects = [];

    /** @var list<string> */
    public array $deleted = [];

    public function put(string $path, string $contents, string $visibility = 'private'): string
    {
        $this->objects[$path] = ['contents' => $contents, 'visibility' => $visibility];

        return $path;
    }

    public function putFile(string $directory, SplFileInfo $file, string $visibility = 'private'): string
    {
        throw new LogicException('The asset pipeline encodes to a string before storing.');
    }

    public function temporaryUrl(string $path, DateTimeInterface $expiresAt): string
    {
        return 'https://cdn.aquashield.test/signed/'.$path;
    }

    public function temporaryUploadUrl(string $path, DateTimeInterface $expiresAt): array
    {
        throw new LogicException('Direct uploads are not part of the profile flow.');
    }

    public function publicUrl(string $path): string
    {
        return str_starts_with($path, 'http') ? $path : 'https://cdn.aquashield.test/'.$path;
    }

    public function copyToLocal(string $path, string $localPath): void
    {
        throw new LogicException('Not used by the asset pipeline.');
    }

    public function putFromPath(string $path, string $localPath, string $visibility = 'private'): string
    {
        throw new LogicException('Not used by the asset pipeline.');
    }

    public function size(string $path): int
    {
        return strlen($this->objects[$path]['contents'] ?? '');
    }

    public function delete(string $path): bool
    {
        $this->deleted[] = $path;
        unset($this->objects[$path]);

        return true;
    }

    public function exists(string $path): bool
    {
        return isset($this->objects[$path]);
    }
}
