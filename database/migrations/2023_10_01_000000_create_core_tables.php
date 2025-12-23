<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Roles Table (Normalization)
        Schema::create('roles', function (Blueprint $table) {
            $table->id(); // tinyint equivalent if desired, but id() is standard
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Seed basic roles
        DB::table('roles')->insertOrIgnore([
            ['name' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'auditor', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'client', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. Users Table Updates (Enhanced)
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->nullable()->after('name');

            // Relation to roles
            $table->foreignId('role_id')->nullable()->after('password')->constrained('roles')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(true)->comment('Force password reset on next login');
            $table->string('avatar_path')->nullable();
        });

        // 3. User Notification Subscriptions (Refactor of 'user_alarm')
        // Allows assigning specific alarms/subscriptions to users (e.g., 'AEROPUERTOS')
        Schema::create('user_notification_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Event type: e.g., 'product_issue', 'audit_completed'
            $table->string('event_type')->default('product_issue');

            // Filter: e.g., filter_key='product_type', filter_value='AEROPUERTOS'
            $table->string('filter_key')->default('product_type');
            $table->string('filter_value')->nullable()->comment('Null means subscribe to all');

            $table->string('channel')->default('email');
            $table->timestamps();

            $table->index(['user_id', 'event_type']);
        });

        // 4. Advertising Spaces (Migrated from 'elemento')
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

        // 5. Performance Optimization: Current States Table
        // Replaces slow views by storing the latest snapshot
        Schema::create('advertising_space_current_states', function (Blueprint $table) {
            $table->unsignedBigInteger('advertising_space_id')->primary();

            $table->string('status')->nullable()->index(); // e.g. 'good', 'bad'
            $table->dateTime('last_audit_date')->nullable();
            $table->integer('total_issues')->default(0);
            $table->string('main_image_path')->nullable();
            $table->timestamps();

            $table->foreign('advertising_space_id')
                ->references('id')
                ->on('advertising_spaces')
                ->onDelete('cascade');
        });

        // 6. Audits (Migrated from 'estado_ele')
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertising_space_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained(); // Auditor
            $table->integer('year');
            $table->integer('week');
            $table->date('audit_date');

            // Scores (1=Good, 3=Bad in old system. We can map to simple status or keep enum)
            $table->string('general_status')->default('good');
            $table->string('illumination_status')->nullable();
            $table->string('material_status')->nullable();
            $table->string('dirt_status')->nullable();
            $table->string('vandalism_status')->nullable();
            $table->string('surroundings_status')->nullable();

            $table->text('observation')->nullable();
            $table->timestamps();

            $table->unique(['advertising_space_id', 'year', 'week']);
        });

        // 7. Audit Photos (Migrated from 'img_elemento')
        Schema::create('audit_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_type')->default('image');
            $table->timestamps();
        });

        // 8. New Feature: Maintenances (Corrective & Preventive)
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertising_space_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'preventive', 'corrective'
            $table->string('status')->default('reported');
            $table->string('priority')->default('medium');

            $table->json('matrix_data')->nullable();

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
        Schema::dropIfExists('advertising_space_current_states');
        Schema::dropIfExists('advertising_spaces');
        Schema::dropIfExists('user_notification_subscriptions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'role_id', 'is_active', 'must_change_password', 'avatar_path']);
        });

        Schema::dropIfExists('roles');
    }
};
