<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace plaintext token storage with a SHA-256 hash.
     *
     * Existing tokens keep working because clients continue to send the same
     * raw 64-char value; only how we store and look it up changes. We backfill
     * the hash for every existing row before dropping the plaintext column.
     */
    public function up(): void
    {
        Schema::table('tokens', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable()->after('name');
        });

        // Backfill the hash for every existing token. Use the query builder so
        // model mutators/casts do not interfere with the raw value.
        DB::table('tokens')->whereNotNull('token')->orderBy('id')->each(function ($row) {
            DB::table('tokens')
                ->where('id', $row->id)
                ->update(['token_hash' => hash('sha256', $row->token)]);
        });

        Schema::table('tokens', function (Blueprint $table) {
            $table->unique('token_hash');
        });

        // Drop the plaintext column's indexes first. SQLite's DROP COLUMN
        // refuses to remove a column that is still referenced by a non-unique
        // index, so both the unique and the plain index must go explicitly.
        Schema::table('tokens', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropIndex(['token']);
        });

        Schema::table('tokens', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }

    /**
     * Reverse the migrations.
     *
     * The original plaintext values cannot be recovered from the hash, so the
     * restored column is left empty.
     */
    public function down(): void
    {
        Schema::table('tokens', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->after('name');
        });

        Schema::table('tokens', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
            $table->dropColumn('token_hash');
        });
    }
};
