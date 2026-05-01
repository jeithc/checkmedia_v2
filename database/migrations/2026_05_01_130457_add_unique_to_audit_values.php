<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purge duplicados pre-existentes (mantener fila con id más alto = última escritura).
        // Sintaxis portable MySQL/SQLite via subquery.
        DB::statement('
            DELETE FROM audit_values
            WHERE id NOT IN (
                SELECT max_id FROM (
                    SELECT MAX(id) AS max_id
                    FROM audit_values
                    GROUP BY audit_id, audit_criterion_id
                ) AS keepers
            )
        ');

        Schema::table('audit_values', function (Blueprint $table) {
            $table->unique(['audit_id', 'audit_criterion_id'], 'audit_values_audit_id_criterion_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('audit_values', function (Blueprint $table) {
            $table->dropUnique('audit_values_audit_id_criterion_id_unique');
        });
    }
};
