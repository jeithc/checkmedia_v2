<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_access_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('label');
            $table->unsignedBigInteger('created_by');
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index('is_revoked');
        });

        Schema::table('audits', function (Blueprint $table) {
            $table->unsignedBigInteger('access_code_id')->nullable()->after('approved_at');
            $table->foreign('access_code_id')->references('id')->on('external_access_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropForeign(['access_code_id']);
            $table->dropColumn('access_code_id');
        });

        Schema::dropIfExists('external_access_codes');
    }
};
