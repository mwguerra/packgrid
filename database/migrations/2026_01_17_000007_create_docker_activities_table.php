<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docker_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('docker_repository_id')
                ->constrained('docker_repositories')
                ->cascadeOnDelete();
            $table->string('type');
            $table->string('tag')->nullable();
            $table->string('digest')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('client_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('docker_repository_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docker_activities');
    }
};
