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
        Schema::create('requisition_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->integer('advisual_requisition_id')->nullable();
            $table->text('advisual_sync_error')->nullable();
            $table->timestamp('advisual_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::table('maintenances', function (Blueprint $table) {
            if (! Schema::hasColumn('maintenances', 'requisition_batch_id')) {
                $table->foreignId('requisition_batch_id')
                    ->nullable()
                    ->constrained('requisition_batches')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('maintenances', 'advisual_requisition_line')) {
                $table->integer('advisual_requisition_line')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            if (Schema::hasColumn('maintenances', 'requisition_batch_id')) {
                $table->dropForeign(['requisition_batch_id']);
                $table->dropColumn('requisition_batch_id');
            }

            if (Schema::hasColumn('maintenances', 'advisual_requisition_line')) {
                $table->dropColumn('advisual_requisition_line');
            }
        });

        Schema::dropIfExists('requisition_batches');
    }
};
