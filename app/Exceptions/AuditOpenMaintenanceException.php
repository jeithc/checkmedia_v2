<?php

namespace App\Exceptions;

class AuditOpenMaintenanceException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Audit has an open maintenance and cannot be edited.');
    }
}
