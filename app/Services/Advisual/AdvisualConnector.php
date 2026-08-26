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

    /**
     * Like statement() but returns the number of affected rows, so a caller can
     * make a conditional UPDATE and learn whether its WHERE still held.
     */
    public function affectingStatement(string $sql, array $bindings = []): int
    {
        if ($this->shouldTryOdbc()) {
            try {
                $pdo = $this->odbcConnection();
                $stmt = $pdo->prepare($sql);
                $stmt->execute($bindings);

                return $stmt->rowCount();
            } catch (\Throwable $eOdbc) {
                return $this->nativeAffectingStatement($sql, $bindings, $eOdbc);
            }
        }

        return $this->nativeAffectingStatement($sql, $bindings);
    }

    protected function nativeAffectingStatement(string $sql, array $bindings, ?\Throwable $eOdbc = null): int
    {
        try {
            return $this->connection()->affectingStatement($sql, $bindings);
        } catch (\Throwable $eNative) {
            throw $this->combinedException($eOdbc, $eNative);
        }
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
