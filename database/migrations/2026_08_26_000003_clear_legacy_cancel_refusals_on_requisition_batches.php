<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Before PR #16 an active-PO cancellation refusal was persisted in
 * advisual_sync_error, so those batches still render as "Error" although
 * the requisition is healthy. Clear that known message; new refusals are no
 * longer stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('requisition_batches')
            ->where('advisual_sync_error', 'like', '%ya tiene órdenes de compra%')
            ->update(['advisual_sync_error' => null]);
    }

    public function down(): void
    {
        // Data fix; nothing to restore.
    }
};
