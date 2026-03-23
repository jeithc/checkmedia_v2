<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('preventive_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('element_type')->comment('Ej: CIRCUITO DIGITAL, VALLA TUBO');
            $table->string('city')->nullable()->comment('Si es null, aplica a nivel nacional');
            $table->integer('frequency_days')->comment('Frecuencia del mantenimiento en días');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Un tipo de elemento no debería estar duplicado por ciudad
            $table->unique(['element_type', 'city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preventive_schedules');
    }
};
