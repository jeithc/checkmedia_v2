<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_values', function (Blueprint $table) {
            $table->text('comment')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('audit_values', function (Blueprint $table) {
            $table->dropColumn('comment');
        });
    }
};
