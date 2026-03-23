<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('audit_criteria', function (Blueprint $table) {
            $table->string('category')->default('general')->after('order_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_criteria', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
