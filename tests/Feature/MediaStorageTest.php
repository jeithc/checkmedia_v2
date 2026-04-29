<?php

use App\Support\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('media files are stored on the configured disk', function () {
    config([
        'media.disk' => 's3',
        'media.visibility' => 'private',
    ]);

    Storage::fake('s3');

    $path = MediaStorage::store(UploadedFile::fake()->image('audit.jpg'), 'audit-photos');

    Storage::disk('s3')->assertExists($path);
    expect($path)->toStartWith('audit-photos/');
});

test('media urls are generated from the configured disk', function () {
    config([
        'filesystems.disks.s3.url' => 'https://cdn.example.test',
        'media.disk' => 's3',
        'media.temporary_urls' => false,
    ]);

    expect(MediaStorage::url('audit-photos/photo.jpg'))
        ->toBe('https://cdn.example.test/audit-photos/photo.jpg');
});

test('configured storage urls are normalized back to paths', function () {
    config([
        'filesystems.disks.s3.url' => 'https://cdn.example.test',
        'media.disk' => 's3',
    ]);

    expect(MediaStorage::normalizePath('https://cdn.example.test/users/avatar.jpg'))
        ->toBe('users/avatar.jpg');
});
