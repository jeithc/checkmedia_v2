<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisition_batches', function (Blueprint $table) {
            // In-flight claim for the Advisual send. Set atomically by
            // RequisitionBatchService::claimSend(); cleared when the send ends.
            $table->timestamp('sending_at')->nullable()->after('advisual_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('requisition_batches', function (Blueprint $table) {
            $table->dropColumn('sending_at');
        });
    }
};
