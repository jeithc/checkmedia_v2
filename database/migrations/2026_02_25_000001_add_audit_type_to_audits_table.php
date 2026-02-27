<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->string('audit_type')->default('general')->after('week');
        });

        // Create new unique index first, so the FK on advertising_space_id
        // has an index to fall back on, then drop the old one.
        Schema::table('audits', function (Blueprint $table) {
            $table->unique(['advertising_space_id', 'year', 'week', 'audit_type']);
        });

        Schema::table('audits', function (Blueprint $table) {
            $table->dropUnique(['advertising_space_id', 'year', 'week']);
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->unique(['advertising_space_id', 'year', 'week']);
        });

        Schema::table('audits', function (Blueprint $table) {
            $table->dropUnique(['advertising_space_id', 'year', 'week', 'audit_type']);
            $table->dropColumn('audit_type');
        });
    }
};
