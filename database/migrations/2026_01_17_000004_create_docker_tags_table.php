<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docker_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('docker_repository_id')
                ->constrained('docker_repositories')
                ->cascadeOnDelete();
            $table->foreignUuid('docker_manifest_id')
                ->constrained('docker_manifests')
                ->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['docker_repository_id', 'name']);
            $table->index('docker_manifest_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docker_tags');
    }
};
