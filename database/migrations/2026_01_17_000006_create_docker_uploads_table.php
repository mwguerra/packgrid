<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docker_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('docker_repository_id')
                ->constrained('docker_repositories')
                ->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('temp_path')->nullable();
            $table->unsignedBigInteger('uploaded_bytes')->default(0);
            $table->unsignedBigInteger('expected_size')->nullable();
            $table->string('expected_digest')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('docker_repository_id');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docker_uploads');
    }
};
