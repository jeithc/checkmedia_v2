<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_external')->default(false)->after('is_superuser');
            $table->string('phone')->nullable()->after('email');
        });

        Schema::table('audits', function (Blueprint $table) {
            $table->string('source')->default('web')->after('audit_purpose');
            $table->string('approval_status')->default('approved')->after('source');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');

            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->index('approval_status');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['approval_status']);
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'approval_status', 'approved_by', 'approved_at', 'rejection_reason']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_external', 'phone']);
        });
    }
};
