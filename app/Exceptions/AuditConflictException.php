<?php

namespace App\Exceptions;

use App\Models\Audit;

class AuditConflictException extends \Exception
{
    public function __construct(public readonly Audit $existing)
    {
        parent::__construct('An audit already exists for this space, year, week and type.');
    }
}
