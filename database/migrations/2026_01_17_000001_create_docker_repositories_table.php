<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docker_repositories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('visibility')->default('private');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('total_size')->default(0);
            $table->unsignedInteger('pull_count')->default(0);
            $table->unsignedInteger('push_count')->default(0);
            $table->unsignedInteger('tag_count')->default(0);
            $table->unsignedInteger('manifest_count')->default(0);
            $table->timestamp('last_push_at')->nullable();
            $table->timestamp('last_pull_at')->nullable();
            $table->timestamps();

            $table->index('visibility');
            $table->index('enabled');
            $table->index('last_push_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docker_repositories');
    }
};
