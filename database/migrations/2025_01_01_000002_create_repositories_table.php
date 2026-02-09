<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('repo_full_name');
            $table->string('url')->unique();
            $table->string('visibility');
            $table->foreignUuid('credential_id')
                ->nullable()
                ->constrained('credentials')
                ->nullOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('package_count')->default(0);
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->string('ref_filter')->nullable();
            $table->timestamps();

            $table->index('visibility');
            $table->index('enabled');
            $table->index('credential_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
