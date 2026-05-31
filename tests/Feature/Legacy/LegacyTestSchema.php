<?php

namespace Tests\Feature\Legacy;

use Illuminate\Support\Facades\Schema;

class LegacyTestSchema
{
    /**
     * Build legacy-shaped tables on the given connection name and point
     * the 'legacy' connection at the same database used by tests.
     */
    public static function build(string $connection = 'legacy'): void
    {
        // Point the 'legacy' connection at the default test connection (sqlite).
        config(['database.connections.legacy' => config('database.connections.'.config('database.default'))]);

        $schema = Schema::connection($connection);

        $schema->dropIfExists('estado_ele');
        $schema->create('estado_ele', function ($table) {
            $table->integer('idEstado')->primary();
            $table->string('espacioCod');
            $table->string('fechaEstado')->nullable();
            $table->integer('semanaEstado')->nullable();
            $table->integer('iluminacionEstado')->default(1);
            $table->integer('estadoEstado')->default(1);
            $table->integer('materialEstado')->default(1);
            $table->integer('entornoEstado')->default(1);
            $table->integer('anomaliaEstado')->default(1);
            $table->integer('idUsuario')->nullable();
        });

        $schema->dropIfExists('elemento');
        $schema->create('elemento', function ($table) {
            $table->string('espacioCod')->primary();
            $table->string('proveedorEle')->nullable();
            $table->string('tipoEle')->nullable();
            $table->string('productoEle')->nullable();
            $table->string('illuminacionEle')->nullable();
            $table->string('espacioProEle')->nullable();
            $table->string('ciudadEle')->nullable();
            $table->string('locacionEle')->nullable();
            $table->string('ubicacionEle')->nullable();
            $table->string('localizacionEle')->nullable();
        });

        $schema->dropIfExists('img_elemento');
        $schema->create('img_elemento', function ($table) {
            $table->integer('idImg')->primary();
            $table->integer('idEstado');
            $table->string('rutaImgElemento');
        });

        $schema->dropIfExists('observaciones');
        $schema->create('observaciones', function ($table) {
            $table->integer('idObserv')->primary();
            $table->integer('idEstado');
            $table->text('notaObserv')->nullable();
        });
    }
}
