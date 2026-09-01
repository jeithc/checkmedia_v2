<?php

namespace App\Services;

use App\Models\AdvertisingSpace;
use App\Models\User;
use Carbon\Carbon;

class AuditSubmissionData
{
    /**
     * @param  array<int,array{value:string,comment?:string}>  $values  keyed by criterion id
     * @param  array<int,\Illuminate\Http\UploadedFile>  $photos
     */
    public function __construct(
        public readonly User $user,
        public readonly AdvertisingSpace $space,
        public readonly string $auditType,
        public readonly string $purpose,
        public readonly array $values,
        public readonly ?string $observation,
        public readonly Carbon $capturedAt,
        public readonly array $photos,
        public readonly ?string $clientUuid = null,
        public readonly bool $allowOverwriteExisting = true,
        public readonly ?\Illuminate\Http\UploadedFile $evidencePdf = null,
    ) {}
}
