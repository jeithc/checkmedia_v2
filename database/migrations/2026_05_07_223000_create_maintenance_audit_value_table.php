<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('maintenance_audit_value', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_value_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['maintenance_id', 'audit_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_audit_value');
    }
};
