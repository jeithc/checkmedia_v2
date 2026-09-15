<?php

namespace App\Exceptions;

use Exception;

/**
 * Advisual (SQL Server) no respondió. Distinto de "el espacio no existe":
 * quien llama debe decirle al usuario que reintente, no que el código es malo.
 */
class AdvisualUnavailableException extends Exception {}
