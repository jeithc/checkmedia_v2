<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Users Table (Enhanced)
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name'); // For 'jcarrillo'
            $table->string('role')->default('auditor'); // admin, auditor
            $table->boolean('is_active')->default(true);
            $table->string('avatar_path')->nullable();
        });

        // Advertising Spaces (Migrated from 'elemento')
        Schema::create('advertising_spaces', function (Blueprint $table) {
            $table->id();
            $table->string('external_code')->unique()->index(); // espacioCod
            $table->string('provider')->nullable(); // proveedorEle
            $table->string('type')->nullable(); // tipoEle (VALLAS, etc)
            $table->string('city')->index(); // ciudadEle
            $table->string('location_name')->nullable(); // locacionEle
            $table->text('address')->nullable(); // localizacionEle / ubicacionEle
            $table->string('product_type')->nullable(); // productoEle
            $table->boolean('has_illumination')->default(false); // illuminacionEle (mapped to boolean)
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
        });

        // Audits (Migrated from 'estado_ele')
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertising_space_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained(); // Auditor
            $table->integer('year');
            $table->integer('week');
            $table->date('audit_date');

            // Scores (1=Good, 3=Bad in old system. We can map to simple status or keep enum)
            // Using polished string status: 'good', 'bad', 'fair'
            $table->string('general_status')->default('good'); // From promedio
            $table->string('illumination_status')->nullable();
            $table->string('material_status')->nullable();
            $table->string('dirt_status')->nullable(); // Material Sucio
            $table->string('vandalism_status')->nullable(); // Material Vandalizado
            $table->string('surroundings_status')->nullable(); // Entorno

            $table->text('observation')->nullable(); // The main comment
            $table->timestamps();

            $table->unique(['advertising_space_id', 'year', 'week']);
        });

        // Audit Photos (Migrated from 'img_elemento')
        Schema::create('audit_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->onDelete('cascade');
            $table->string('file_path'); // Path in S3 or local
            $table->string('file_type')->default('image'); // image, video
            $table->timestamps();
        });

        // New Feature: Maintenances (Corrective & Preventive)
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertising_space_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'preventive', 'corrective'
            $table->string('status')->default('reported'); // reported, in_progress, completed, closed
            $table->string('priority')->default('medium'); // low, medium, high, critical

            // For Matrix/Calculations
            $table->json('matrix_data')->nullable(); // Store the structural/environmental check results

            $table->text('description');
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->decimal('final_cost', 10, 2)->nullable();

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenances');
        Schema::dropIfExists('audit_photos');
        Schema::dropIfExists('audits');
        Schema::dropIfExists('advertising_spaces');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'role', 'is_active', 'avatar_path']);
        });
    }
};
