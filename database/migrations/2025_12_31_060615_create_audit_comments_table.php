<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->text('message');
            $table->string('type')->default('comment'); // comment, status_change, resolution
            $table->timestamps();
        });

        Schema::table('audits', function (Blueprint $table) {
            $table->string('resolution_photo_path')->nullable();
            $table->text('resolution_comment')->nullable();
            $table->timestamp('resolved_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn(['resolution_photo_path', 'resolution_comment', 'resolved_at']);
        });
        Schema::dropIfExists('audit_comments');
    }
};
