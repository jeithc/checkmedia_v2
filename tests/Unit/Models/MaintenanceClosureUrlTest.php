<?php

use App\Models\Maintenance;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('closure_document_url returns s3 disk url', function () {
    Storage::fake('s3');

    $maintenance = new Maintenance(['closure_document_path' => 'maintenance-closures/c.pdf']);

    expect($maintenance->closure_document_url)
        ->toBe(Storage::disk('s3')->url('maintenance-closures/c.pdf'));
});
