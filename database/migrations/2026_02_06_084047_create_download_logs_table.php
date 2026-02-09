<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_id')->constrained('repositories')->cascadeOnDelete();
            $table->foreignUuid('token_id')->nullable()->constrained('tokens')->nullOnDelete();
            $table->string('package_version');
            $table->string('format');
            $table->string('client_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('repository_id');
            $table->index('token_id');
            $table->index('format');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_logs');
    }
};
