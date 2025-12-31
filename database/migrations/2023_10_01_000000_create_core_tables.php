<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Users Table
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name')->nullable();
            $table->string('role')->default('auditor');
            $table->boolean('is_active')->default(true);
            $table->string('avatar_path')->nullable();
        });

        // 1. Physical Assets (Stable Data)
        Schema::create('advertising_spaces', function (Blueprint $table) {
            $table->id();
            $table->string('external_code')->unique()->index(); // "EspacioCodigo"
            $table->string('provider')->nullable(); // "ProveedorNombre"
            $table->string('type')->nullable(); // "TipoElementoNombre"
            $table->string('category')->nullable(); //"ProductoElementoNombre"
            $table->string('illumination_type')->nullable(); // "IluminacionNombre"

            // Ownership & Third Party
            $table->string('ownership')->nullable()->comment('P: Propio, etc.'); // "espacioProEle"
            $table->boolean('is_third_party')->default(false); // "tercero"
            $table->foreignId('third_party_user_id')->nullable()->constrained('users'); // "usuario_tercero"
            $table->timestamp('third_party_modified_at')->nullable(); // "fecha_tercero"

            // Location Info
            $table->string('city')->index(); // "CiudadNombre"
            $table->string('location_name')->nullable(); // "LocacionNombre"
            $table->text('address')->nullable(); // "EspacioUbicacion"
            $table->string('zone')->nullable(); // "LocalizacionNombre"

            $table->geography('location', subtype: 'point', srid: 4326)->nullable();
            // $table->decimal('latitude', 10, 8)->nullable();
            // $table->decimal('longitude', 11, 8)->nullable();

            $table->timestamps();
        });

        // 2. Commercial Assignments (Volatile/Time-based Data)
        Schema::create('commercial_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertising_space_id')->constrained()->onDelete('cascade');

            $table->integer('year');
            $table->integer('week');

            $table->string('client_name'); // "clientenombre"
            $table->string('contract_code')->nullable(); // "EspacioCodigoPlano"
            $table->string('product_name')->nullable(); // "ProductoNombre" - e.g. "Coca Cola Zero"

            // Can add start_date/end_date if we improve MSSQL query
            // $table->date('start_date')->nullable();
            // $table->date('end_date')->nullable();

            $table->timestamps();

            // Index for quick lookup of "Who is on this space this week?"
            $table->index(['advertising_space_id', 'year', 'week']);
        });

        // 3. Audits
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertising_space_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained();

            $table->integer('year');
            $table->integer('week');
            $table->date('audit_date');

            // Snapshot of statuses
            $table->string('general_status')->default('good');
            $table->text('observation')->nullable();

            $table->timestamps();

            $table->unique(['advertising_space_id', 'year', 'week']);
        });

        // 3.1 Dynamic Audit Criteria (Configuration)
        Schema::create('audit_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Iluminación", "Limpieza", "Estructural"
            $table->string('key')->unique(); // "illumination", "cleaning" (for code ref)
            $table->string('type')->default('boolean'); // boolean, scale_1_5, text
            $table->boolean('is_active')->default(true);
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });

        // 3.2 Audit Values (Results)
        Schema::create('audit_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->onDelete('cascade');
            $table->foreignId('audit_criterion_id')->constrained('audit_criteria')->onDelete('cascade');

            $table->string('value'); // "good", "bad", "1", "5"
            $table->text('comment')->nullable();

            $table->timestamps();
        });

        // 3.3 Uploaded Photos
        Schema::create('audit_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_type')->default('image');
            $table->timestamps();
        });

        // 4. Maintenances
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertising_space_id')->constrained()->onDelete('cascade');

            $table->string('type'); // 'preventive', 'corrective'
            $table->string('category')->nullable(); // 'estructural', 'electrico', 'ambiental', 'material' (Requested in meeting)
            $table->string('status')->default('reported');
            $table->string('priority')->default('medium');

            $table->json('matrix_data')->nullable(); // Checklist results
            $table->text('description')->nullable();

            // Cost & Execution tracking
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->decimal('final_cost', 10, 2)->nullable();

            // Linking to 'Commercial' context (Optional: The client might pay for it?)
            // $table->foreignId('commercial_booking_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenances');
        Schema::dropIfExists('audit_photos');
        Schema::dropIfExists('audit_values');
        Schema::dropIfExists('audits');
        Schema::dropIfExists('audit_criteria');
        Schema::dropIfExists('commercial_bookings');
        Schema::dropIfExists('advertising_spaces');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'role', 'is_active', 'avatar_path']);
        });
    }
};
