<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            if (! Schema::hasColumn('maintenances', 'advisual_purchase_order_id')) {
                $table->unsignedBigInteger('advisual_purchase_order_id')->nullable();
            }

            if (! Schema::hasColumn('maintenances', 'advisual_purchase_order_line_id')) {
                $table->unsignedBigInteger('advisual_purchase_order_line_id')->nullable();
            }

            if (! Schema::hasColumn('maintenances', 'advisual_purchase_order_description')) {
                $table->text('advisual_purchase_order_description')->nullable();
            }

            if (! Schema::hasColumn('maintenances', 'advisual_purchase_order_quantity')) {
                $table->decimal('advisual_purchase_order_quantity', 15, 4)->nullable();
            }

            if (! Schema::hasColumn('maintenances', 'advisual_purchase_order_unit_price')) {
                $table->decimal('advisual_purchase_order_unit_price', 15, 2)->nullable();
            }

            if (! Schema::hasColumn('maintenances', 'advisual_purchase_order_total')) {
                $table->decimal('advisual_purchase_order_total', 15, 2)->nullable();
            }

            if (! Schema::hasColumn('maintenances', 'advisual_purchase_order_created_at')) {
                $table->dateTime('advisual_purchase_order_created_at')->nullable();
            }

            if (! Schema::hasColumn('maintenances', 'advisual_purchase_order_committed_at')) {
                $table->dateTime('advisual_purchase_order_committed_at')->nullable();
            }

            if (! Schema::hasColumn('maintenances', 'advisual_purchase_order_executed_at')) {
                $table->dateTime('advisual_purchase_order_executed_at')->nullable();
            }

            if (! Schema::hasColumn('maintenances', 'advisual_purchase_order_last_checked_at')) {
                $table->dateTime('advisual_purchase_order_last_checked_at')->nullable();
            }

            if (! Schema::hasColumn('maintenances', 'advisual_purchase_order_sync_error')) {
                $table->text('advisual_purchase_order_sync_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $columnsToDrop = [];

            foreach ([
                'advisual_purchase_order_id',
                'advisual_purchase_order_line_id',
                'advisual_purchase_order_description',
                'advisual_purchase_order_quantity',
                'advisual_purchase_order_unit_price',
                'advisual_purchase_order_total',
                'advisual_purchase_order_created_at',
                'advisual_purchase_order_committed_at',
                'advisual_purchase_order_executed_at',
                'advisual_purchase_order_last_checked_at',
                'advisual_purchase_order_sync_error',
            ] as $column) {
                if (Schema::hasColumn('maintenances', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
