# SQLite to PostgreSQL Migration Command

**Date:** 2026-04-05
**Status:** Approved

## Overview

Artisan command `packgrid:migrate-to-postgres` that migrates all application data from the current SQLite database to a PostgreSQL instance. Uses Laravel's DB facade (no Eloquent models) to read/write all tables including pivots, preserving IDs, UUIDs, timestamps, and encrypted values.

## Command Signature

```
packgrid:migrate-to-postgres
    {database? : PostgreSQL database name}
    {username? : PostgreSQL username}
    {--host=127.0.0.1 : PostgreSQL host}
    {--port=5432 : PostgreSQL port}
    {--verify : Verify data integrity after migration}
```

- `database` and `username`: accepted as arguments or prompted if missing
- Password: always prompted with hidden input, never accepted as argument
- Production: detected via `app()->environment('production')`, shows warning requiring explicit confirmation

## Table Migration Order

Ordered to respect foreign key dependencies:

1. `users`
2. `settings`
3. `credentials`
4. `tokens`
5. `repositories`
6. `docker_repositories`
7. `docker_blobs`
8. `docker_manifests`
9. `docker_tags`
10. `docker_uploads`
11. `docker_activities`
12. `sync_logs`
13. `download_logs`
14. `repository_token` (pivot)
15. `clone_repository_token` (pivot)
16. `docker_repository_token` (pivot)
17. `docker_blob_repository` (pivot)

**Skipped:** `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`, `password_reset_tokens`

## SQLite to PostgreSQL Compatibility

### Booleans
SQLite stores `0/1`, PostgreSQL expects `true/false`. Convert for:
- `repositories`: `enabled`, `clone_enabled`
- `docker_repositories`: `enabled`
- `tokens`: `enabled`
- `settings`: `composer_enabled`, `npm_enabled`, `docker_enabled`, `git_enabled`

### JSON Columns
Ensure proper JSON encoding for:
- `tokens`: `allowed_ips`, `allowed_domains`
- `docker_manifests`: `layer_digests`

### Encrypted Columns
`credentials.token` transfers as-is (encryption is app-level via `APP_KEY`).

### Auto-increment Sequences
After inserting into `users` and `settings` (integer PKs), reset PostgreSQL sequences to `MAX(id) + 1`.

### UUIDs
Transfer as plain strings — PostgreSQL's native `uuid` column type accepts standard UUID string format directly.

### Timestamps
SQLite `YYYY-MM-DD HH:MM:SS` strings are accepted natively by PostgreSQL `timestamp` columns.

### Pivot Table Timestamps
`docker_blob_repository` has `created_at`/`updated_at` — copied as-is.

### Text Column Types
PostgreSQL only has `text` (unlimited) — all SQLite text variants (`text`, `mediumText`) map to it without issues.

### Note on Table Ordering
`docker_blobs` has no FK to `docker_repositories` — it is linked only through the `docker_blob_repository` pivot table. The ordering must not be changed assuming a direct dependency.

## Migration Flow

1. Prompt for missing connection details (database, username, password)
2. Configure runtime `pgsql_target` connection
3. Test connection — fail early with clear error
4. Run `php artisan migrate --database=pgsql_target` on the target
5. Detect existing data — ask user to wipe (`migrate:fresh --database=pgsql_target` + re-migrate) or abort
6. Disable FK trigger enforcement: `SET session_replication_role = 'replica'` (Laravel's default constraints are `NOT DEFERRABLE`, so `SET CONSTRAINTS ALL DEFERRED` would silently do nothing)
7. For each table in order:
   - Read in chunks of 500 from SQLite (100 for `docker_manifests` due to large `content` column)
   - Apply type conversions (booleans, JSON)
   - Insert into PostgreSQL
   - Show progress with row count
8. Reset auto-increment sequences for `users` and `settings`
9. Re-enable FK triggers: `SET session_replication_role = 'default'`
10. If `--verify`: compare row counts per table

**Transaction:** Entire data copy wrapped in a PostgreSQL transaction — rollback on any failure.

## Verification (`--verify`)

- Counts rows per table on both SQLite and PostgreSQL
- Reports per table: checkmark (match) or X (mismatch with both counts)
- Exits with non-zero code if any mismatch found

## Testing

**File:** `tests/Feature/MigrateToPostgresCommandTest.php`

Uses docker-local PostgreSQL. Test is skipped if postgres is unavailable.

**Assertions:**
- Row counts match for every migrated table
- UUID preservation (known credential/repository ID)
- Boolean conversion (`enabled` fields are actual booleans)
- JSON column integrity (`allowed_ips` on token)
- Encrypted column passthrough (`credentials.token` decrypts)
- Sequence reset (new User insert doesn't collide)
- Pivot table data (`repository_token` entries)
- Timestamps preserved accurately

**Connection:** Configured at runtime using env vars `TEST_PG_DATABASE`, `TEST_PG_USERNAME`, `TEST_PG_PASSWORD` with docker-local defaults.
