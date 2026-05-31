<?php

use App\Models\AuditPhoto;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('url accessor returns s3 disk url for the file path', function () {
    Storage::fake('s3');

    $photo = new AuditPhoto(['file_path' => 'audit-photos/example.jpg']);

    expect($photo->url)->toBe(Storage::disk('s3')->url('audit-photos/example.jpg'));
});
