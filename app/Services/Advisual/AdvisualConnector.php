<?php

namespace App\Services\Advisual;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;

/**
 * Shared connector for the Advisual SQL Server database.
 *
 * Tries FreeTDS ODBC first (required on Hostinger shared hosting where
 * sqlsrv is unavailable), then falls back to the native sqlsrv driver
 * configured on the `advisual` Laravel connection.
 */
class AdvisualConnector
{
    public function __construct(protected ?DatabaseManager $database = null) {}

    public function selectOne(string $sql, array $bindings = []): ?object
    {
        if ($this->shouldTryOdbc()) {
            try {
                $pdo = $this->odbcConnection();
                $stmt = $pdo->prepare($sql);
                $stmt->execute($bindings);
                $row = $stmt->fetch(\PDO::FETCH_OBJ);

                return $row ?: null;
            } catch (\Throwable $eOdbc) {
                return $this->nativeSelectOne($sql, $bindings, $eOdbc);
            }
        }

        return $this->nativeSelectOne($sql, $bindings);
    }

    public function select(string $sql, array $bindings = []): array
    {
        if ($this->shouldTryOdbc()) {
            try {
                $pdo = $this->odbcConnection();
                $stmt = $pdo->prepare($sql);
                $stmt->execute($bindings);

                return $stmt->fetchAll(\PDO::FETCH_OBJ) ?: [];
            } catch (\Throwable $eOdbc) {
                return $this->nativeSelect($sql, $bindings, $eOdbc);
            }
        }

        return $this->nativeSelect($sql, $bindings);
    }

    public function statement(string $sql, array $bindings = []): void
    {
        if ($this->shouldTryOdbc()) {
            try {
                $pdo = $this->odbcConnection();
                $stmt = $pdo->prepare($sql);
                $stmt->execute($bindings);

                return;
            } catch (\Throwable $eOdbc) {
                $this->nativeStatement($sql, $bindings, $eOdbc);

                return;
            }
        }

        $this->nativeStatement($sql, $bindings);
    }

    protected function shouldTryOdbc(): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        if (! config('services.advisual.use_odbc', true)) {
            return false;
        }

        return extension_loaded('pdo_odbc');
    }

    protected function odbcConnection(): \PDO
    {
        $username = config('database.connections.advisual.username');
        $password = config('database.connections.advisual.password');
        $database = config('database.connections.advisual.database');
        $host = config('database.connections.advisual.host');
        $port = config('database.connections.advisual.port', '1433');

        $dsn = "odbc:Driver=FreeTDS;Server={$host};Port={$port};Database={$database};TDS_Version=7.4;";

        // This is the path actually used on Hostinger, and the server sits on a
        // separate VPS, so the session crosses the public internet. FreeTDS does
        // not request encryption unless asked, which leaves the query and result
        // data unprotected. Enabling it requires a server certificate FreeTDS
        // can negotiate, so it is opt-in per environment rather than a silent
        // default that would break the integration.
        if (config('database.connections.advisual.encrypt')) {
            $dsn .= 'Encryption=require;';
        }

        return new \PDO($dsn, $username, $password);
    }

    protected function nativeSelectOne(string $sql, array $bindings, ?\Throwable $eOdbc = null): ?object
    {
        try {
            $result = $this->connection()->selectOne($sql, $bindings);

            return $result ?: null;
        } catch (\Throwable $eNative) {
            throw $this->combinedException($eOdbc, $eNative);
        }
    }

    protected function nativeSelect(string $sql, array $bindings, ?\Throwable $eOdbc = null): array
    {
        try {
            return $this->connection()->select($sql, $bindings);
        } catch (\Throwable $eNative) {
            throw $this->combinedException($eOdbc, $eNative);
        }
    }

    protected function nativeStatement(string $sql, array $bindings, ?\Throwable $eOdbc = null): void
    {
        try {
            $this->connection()->statement($sql, $bindings);
        } catch (\Throwable $eNative) {
            throw $this->combinedException($eOdbc, $eNative);
        }
    }

    protected function connection()
    {
        return $this->database
            ? $this->database->connection('advisual')
            : DB::connection('advisual');
    }

    protected function combinedException(?\Throwable $eOdbc, \Throwable $eNative): \Exception
    {
        if ($eOdbc) {
            return new \Exception('ODBC Error: '.$eOdbc->getMessage().' | Native Error: '.$eNative->getMessage(), 0, $eNative);
        }

        return new \Exception('Advisual Native Error: '.$eNative->getMessage(), 0, $eNative);
    }
}
