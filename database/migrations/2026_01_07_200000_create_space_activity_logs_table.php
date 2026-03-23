<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertising_space_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('audit_id')->nullable()->constrained()->onDelete('set null');
            
            // Activity type: audit_created, audit_updated, marked_third_party, resolution_uploaded, status_changed, etc.
            $table->string('activity_type');
            
            // Human readable description
            $table->string('description');
            
            // Additional data (JSON) - stores old/new values, metadata, etc.
            $table->json('metadata')->nullable();
            
            // For quick filtering
            $table->integer('year')->nullable();
            $table->integer('week')->nullable();
            
            $table->timestamps();
            
            // Index for quick lookups
            $table->index(['advertising_space_id', 'created_at']);
            $table->index(['activity_type']);
        });

        // Add resolution fields to audits if not exists
        // Note: Run this separately if audits table doesn't have these columns:
        // ALTER TABLE audits ADD COLUMN resolution_photo_path VARCHAR(255) NULL;
        // ALTER TABLE audits ADD COLUMN resolution_comment TEXT NULL;
        // ALTER TABLE audits ADD COLUMN resolved_at TIMESTAMP NULL;
    }

    public function down(): void
    {
        Schema::dropIfExists('space_activity_logs');
    }
};
