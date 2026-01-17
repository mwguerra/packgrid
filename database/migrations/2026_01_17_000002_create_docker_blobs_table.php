<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docker_blobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('digest')->unique();
            $table->unsignedBigInteger('size');
            $table->string('media_type')->nullable();
            $table->string('storage_path');
            $table->unsignedInteger('reference_count')->default(0);
            $table->timestamps();

            $table->index('digest');
            $table->index('reference_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docker_blobs');
    }
};
