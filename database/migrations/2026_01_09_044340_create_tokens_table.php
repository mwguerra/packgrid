<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->json('allowed_ips')->nullable();
            $table->json('allowed_domains')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('token');
            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokens');
    }
};
