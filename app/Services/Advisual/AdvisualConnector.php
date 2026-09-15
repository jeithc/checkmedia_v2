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
                $row = $this->odbcExecute($sql, $bindings)->fetch(\PDO::FETCH_OBJ);

                return $row ?: null;
            } catch (\Throwable $eOdbc) {
                return $this->nativeSelectOne($sql, $bindings, $eOdbc);
            }
        }

        return $this->nativeSelectOne($sql, $bindings);
    }

    /**
     * First row carrying `$column` across every result set the statement
     * produces. Needed for `INSERT ...; SELECT SCOPE_IDENTITY()` batches,
     * where the id may land in a later rowset.
     */
    public function selectOneAcrossRowsets(string $sql, array $bindings, string $column, ?string $fallbackSql = null): ?object
    {
        if ($this->shouldTryOdbc()) {
            try {
                $pdo = $this->odbcConnection();
                $stmt = $this->odbcExecute($sql, $bindings, $pdo);

                do {
                    $row = $stmt->fetch(\PDO::FETCH_OBJ);
                    if ($row && isset($row->{$column})) {
                        return $row;
                    }
                } while ($stmt->nextRowset());

                // FreeTDS a veces ejecuta el INSERT sin exponer el rowset del
                // SCOPE_IDENTITY(): se pregunta por la identidad en la MISMA
                // conexión, o el id de una fila ya insertada se pierde.
                if ($fallbackSql) {
                    $row = $pdo->query($fallbackSql)->fetch(\PDO::FETCH_OBJ);

                    if ($row && isset($row->{$column})) {
                        return $row;
                    }
                }

                return null;
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
                return $this->odbcExecute($sql, $bindings)->fetchAll(\PDO::FETCH_OBJ) ?: [];
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
                $this->odbcExecute($sql, $bindings);

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
                return $this->odbcExecute($sql, $bindings)->rowCount();
            } catch (\Throwable $eOdbc) {
                return $this->nativeAffectingStatement($sql, $bindings, $eOdbc);
            }
        }

        return $this->nativeAffectingStatement($sql, $bindings);
    }

    /**
     * Run a statement over ODBC. Some FreeTDS/pdo_odbc builds reject every
     * bound parameter with HY090 ("Invalid string or buffer length"); when
     * that happens the same SQL is retried with the bindings quoted inline.
     */
    protected function odbcExecute(string $sql, array $bindings, ?\PDO $pdo = null): \PDOStatement
    {
        $pdo ??= $this->odbcConnection();

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);

            return $stmt;
        } catch (\PDOException $e) {
            if (! $bindings || ! $this->isParameterBindingFailure($e)) {
                throw $e;
            }

            return $pdo->query($this->interpolate($sql, $bindings));
        }
    }

    protected function isParameterBindingFailure(\PDOException $e): bool
    {
        return ($e->getCode() === 'HY090')
            || str_contains($e->getMessage(), 'HY090')
            || str_contains($e->getMessage(), 'Invalid string or buffer length');
    }

    /**
     * Replace each `?` placeholder (outside of string literals) with its
     * quoted binding, T-SQL style.
     */
    public function interpolate(string $sql, array $bindings): string
    {
        $bindings = array_values($bindings);
        $out = '';
        $inString = false;
        $i = 0;
        $length = strlen($sql);

        for ($pos = 0; $pos < $length; $pos++) {
            $char = $sql[$pos];

            if ($char === "'") {
                $inString = ! $inString;
                $out .= $char;

                continue;
            }

            if ($char === '?' && ! $inString) {
                if (! array_key_exists($i, $bindings)) {
                    throw new \InvalidArgumentException('Advisual: not enough bindings for the placeholders in the query.');
                }

                $out .= $this->quote($bindings[$i]);
                $i++;

                continue;
            }

            $out .= $char;
        }

        return $out;
    }

    protected function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        $value = (string) $value;

        if (str_contains($value, "\0")) {
            throw new \InvalidArgumentException('Advisual: NUL byte in a query binding.');
        }

        return "N'".str_replace("'", "''", $value)."'";
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
