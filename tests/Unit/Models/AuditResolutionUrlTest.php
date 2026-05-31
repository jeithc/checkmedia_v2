<?php

use App\Models\Audit;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('resolution_photo_url returns s3 disk url', function () {
    Storage::fake('s3');

    $audit = new Audit(['resolution_photo_path' => 'audit_resolutions/r.jpg']);

    expect($audit->resolution_photo_url)
        ->toBe(Storage::disk('s3')->url('audit_resolutions/r.jpg'));
});
