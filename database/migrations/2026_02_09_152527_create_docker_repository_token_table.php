<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('docker_repository_token', function (Blueprint $table) {
            $table->foreignUuid('docker_repository_id')->constrained('docker_repositories')->cascadeOnDelete();
            $table->foreignUuid('token_id')->constrained('tokens')->cascadeOnDelete();
            $table->primary(['docker_repository_id', 'token_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('docker_repository_token');
    }
};
