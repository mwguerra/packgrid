<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docker_blob_repository', function (Blueprint $table) {
            $table->foreignUuid('docker_blob_id')
                ->constrained('docker_blobs')
                ->cascadeOnDelete();
            $table->foreignUuid('docker_repository_id')
                ->constrained('docker_repositories')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['docker_blob_id', 'docker_repository_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docker_blob_repository');
    }
};
