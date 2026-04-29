<?php

namespace App\Support;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaStorage
{
    public static function disk(): string
    {
        return config('media.disk', 'public');
    }

    public static function visibility(): string
    {
        return config('media.visibility', 'public');
    }

    public static function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, [
            'disk' => self::disk(),
            'visibility' => self::visibility(),
        ]);

        return self::ensureStored($path);
    }

    public static function putFile(string $directory, File|UploadedFile $file): string
    {
        $path = Storage::disk(self::disk())->putFile($directory, $file, [
            'visibility' => self::visibility(),
        ]);

        return self::ensureStored($path);
    }

    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $path = ltrim($path, '/');

        $disk = Storage::disk(self::disk());

        if (config('media.temporary_urls', false) && method_exists($disk, 'temporaryUrl')) {
            return $disk->temporaryUrl(
                $path,
                now()->addMinutes(config('media.temporary_url_ttl_minutes', 30))
            );
        }

        return $disk->url($path);
    }

    public static function normalizePath(?string $path): ?string
    {
        if (blank($path)) {
            return $path;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return str_contains($path, '/storage/')
                ? explode('/storage/', $path, 2)[1]
                : self::pathFromConfiguredUrl($path) ?? $path;
        }

        return $path;
    }

    public static function pathFromConfiguredUrl(string $url): ?string
    {
        $baseUrl = config('filesystems.disks.'.self::disk().'.url');

        if (! is_string($baseUrl) || blank($baseUrl)) {
            return null;
        }

        $baseUrl = rtrim($baseUrl, '/').'/';

        if (! str_starts_with($url, $baseUrl)) {
            return null;
        }

        return substr($url, strlen($baseUrl));
    }

    private static function ensureStored(string|false $path): string
    {
        if ($path === false) {
            throw new RuntimeException('Unable to store media file.');
        }

        return $path;
    }
}
