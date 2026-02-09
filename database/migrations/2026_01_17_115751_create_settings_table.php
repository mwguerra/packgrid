<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('composer_enabled')->default(true);
            $table->boolean('npm_enabled')->default(true);
            $table->boolean('docker_enabled')->default(true);
            $table->timestamps();
        });

        // Insert default settings row
        DB::table('settings')->insert([
            'composer_enabled' => true,
            'npm_enabled' => true,
            'docker_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
