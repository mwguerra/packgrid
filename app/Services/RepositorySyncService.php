<?php

namespace App\Services;

use App\Enums\PackageFormat;
use App\Enums\RepositoryVisibility;
use App\Enums\SyncStatus;
use App\Models\Repository;
use App\Models\SyncLog;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class RepositorySyncService
{
    public function __construct(
        private readonly RepositoryMetadataBuilder $builder,
        private readonly PackageMetadataStore $composerStore,
        private readonly PackageIndexBuilder $composerIndexBuilder,
        private readonly NpmMetadataStore $npmStore,
        private readonly NpmIndexBuilder $npmIndexBuilder
    ) {}

    public function sync(Repository $repository): SyncLog
    {
        $startedAt = Carbon::now();
        $log = SyncLog::create([
            'repository_id' => $repository->id,
            'status' => SyncStatus::Fail,
            'started_at' => $startedAt,
        ]);

        try {
            if (! $repository->enabled) {
                throw new RuntimeException('Repository is disabled.');
            }

            if ($repository->visibility === RepositoryVisibility::PrivateRepo && ! $repository->credential) {
                throw new RuntimeException('Private repository requires a credential.');
            }

            $packages = $this->builder->build($repository);

            // Use the appropriate store and index builder based on format
            $format = $repository->format ?? PackageFormat::Composer;

            if ($format === PackageFormat::Npm) {
                $this->npmStore->writeRepositoryMetadata($repository->id, $packages);
                $this->npmIndexBuilder->rebuild();
            } else {
                $this->composerStore->writeRepositoryMetadata($repository->id, $packages);
                $this->composerIndexBuilder->rebuild();
            }

            $packageCount = 0;
            foreach ($packages as $versions) {
                $packageCount += count($versions);
            }

            $repository->forceFill([
                'package_count' => $packageCount,
                'last_sync_at' => $startedAt,
                'last_error' => null,
            ])->save();

            $log->forceFill([
                'status' => SyncStatus::Success,
                'finished_at' => Carbon::now(),
                'error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $repository->forceFill([
                'last_sync_at' => $startedAt,
                'last_error' => $exception->getMessage(),
            ])->save();

            $log->forceFill([
                'status' => SyncStatus::Fail,
                'finished_at' => Carbon::now(),
                'error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }

        return $log;
    }
}
