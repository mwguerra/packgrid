<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docker_manifests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('docker_repository_id')
                ->constrained('docker_repositories')
                ->cascadeOnDelete();
            $table->string('digest')->unique();
            $table->string('media_type');
            $table->mediumText('content');
            $table->unsignedBigInteger('size');
            $table->json('layer_digests')->nullable();
            $table->string('config_digest')->nullable();
            $table->string('architecture')->nullable();
            $table->string('os')->nullable();
            $table->timestamps();

            $table->index('docker_repository_id');
            $table->index('digest');
            $table->index(['architecture', 'os']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docker_manifests');
    }
};
