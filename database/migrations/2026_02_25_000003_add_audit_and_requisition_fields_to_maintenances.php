<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            // Only add columns that don't already exist
            if (!Schema::hasColumn('maintenances', 'requested_by')) {
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete()->after('audit_id');
            }
            if (!Schema::hasColumn('maintenances', 'requested_at')) {
                $table->datetime('requested_at')->nullable()->after(Schema::hasColumn('maintenances', 'requested_by') ? 'requested_by' : 'audit_id');
            }
            if (!Schema::hasColumn('maintenances', 'advisual_synced_at')) {
                $table->datetime('advisual_synced_at')->nullable()->after('advisual_requisition_id');
            }
            if (!Schema::hasColumn('maintenances', 'advisual_sync_error')) {
                $table->text('advisual_sync_error')->nullable()->after(Schema::hasColumn('maintenances', 'advisual_synced_at') ? 'advisual_synced_at' : 'advisual_requisition_id');
            }
            if (!Schema::hasColumn('maintenances', 'closed_by')) {
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete()->after('final_cost');
            }
            if (!Schema::hasColumn('maintenances', 'closed_at')) {
                $table->datetime('closed_at')->nullable()->after(Schema::hasColumn('maintenances', 'closed_by') ? 'closed_by' : 'final_cost');
            }
            if (!Schema::hasColumn('maintenances', 'closure_document_path')) {
                $table->string('closure_document_path')->nullable()->after(Schema::hasColumn('maintenances', 'closed_at') ? 'closed_at' : 'final_cost');
            }
            if (!Schema::hasColumn('maintenances', 'closure_comment')) {
                $table->text('closure_comment')->nullable()->after(Schema::hasColumn('maintenances', 'closure_document_path') ? 'closure_document_path' : 'final_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['requested_by', 'requested_at', 'advisual_synced_at', 'advisual_sync_error', 'closed_by', 'closed_at', 'closure_document_path', 'closure_comment'] as $col) {
                if (Schema::hasColumn('maintenances', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                // Drop foreign keys first if they exist
                if (in_array('requested_by', $columnsToDrop)) {
                    $table->dropForeign(['requested_by']);
                }
                if (in_array('closed_by', $columnsToDrop)) {
                    $table->dropForeign(['closed_by']);
                }
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
