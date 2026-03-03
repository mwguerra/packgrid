<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->whereNull('git_enabled')->update(['git_enabled' => false]);
    }

    public function down(): void
    {
        // No reversal needed — data was already null-defaulted
    }
};
