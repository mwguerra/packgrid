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
        Schema::create('repository_token', function (Blueprint $table) {
            $table->foreignUuid('repository_id')->constrained('repositories')->cascadeOnDelete();
            $table->foreignUuid('token_id')->constrained('tokens')->cascadeOnDelete();
            $table->primary(['repository_id', 'token_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repository_token');
    }
};
