<?php

use App\Http\Controllers\Docker\BlobController;
use App\Http\Controllers\Docker\CatalogController;
use App\Http\Controllers\Docker\ManifestController;
use App\Http\Controllers\Docker\TagsController;
use App\Http\Controllers\Docker\UploadController;
use App\Http\Controllers\Docker\VersionController;
use App\Http\Controllers\NpmMetadataController;
use App\Http\Controllers\NpmProxyController;
use App\Http\Controllers\PackageMetadataController;
use App\Http\Controllers\PackageProxyController;
use App\Http\Middleware\RedirectToSetupIfNoUsers;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->to('/admin/login');
})->middleware(RedirectToSetupIfNoUsers::class);

/*
|--------------------------------------------------------------------------
| Composer Registry Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['packgrid.token', 'feature:composer'])->group(function () {
    Route::get('/packages.json', [PackageMetadataController::class, 'index']);
    Route::get('/p/{vendor}/{package}.json', [PackageMetadataController::class, 'show']);
    Route::get('/dist/{owner}/{repo}/{ref}.zip', [PackageProxyController::class, 'download'])
        ->where('ref', '.*');
});

/*
|--------------------------------------------------------------------------
| NPM Registry Routes
|--------------------------------------------------------------------------
*/
Route::prefix('npm')->middleware(['packgrid.token', 'feature:npm'])->group(function () {
    // Scoped packages: @scope/package (two path segments)
    Route::get('/@{scope}/{package}', [NpmMetadataController::class, 'showScoped']);

    // Scoped packages with URL-encoded slash: @scope%2fpackage (single path segment)
    // npm clients often URL-encode the scoped package name
    Route::get('/{scopedPackage}', [NpmMetadataController::class, 'showScopedEncoded'])
        ->where('scopedPackage', '@[^/]+%2[fF][^/]+');

    // Non-scoped packages
    Route::get('/{package}', [NpmMetadataController::class, 'show'])
        ->where('package', '[^@/]+');

    // Tarball downloads
    Route::get('/-/{owner}/{repo}/{ref}.tgz', [NpmProxyController::class, 'download'])
        ->where('ref', '.*');
});

/*
|--------------------------------------------------------------------------
| Docker Registry v2 API Routes
|--------------------------------------------------------------------------
*/

// Version check (no auth required per OCI spec)
Route::get('/v2/', VersionController::class);

// Authenticated Docker Registry routes
Route::prefix('v2')->middleware(['docker.auth', 'feature:docker'])->group(function () {
    // Catalog (list all repositories)
    Route::get('/_catalog', CatalogController::class);

    // Repository-scoped routes (name can contain slashes, e.g., myorg/myapp)
    Route::prefix('{name}')->where(['name' => '[a-z0-9]+([._\/-][a-z0-9]+)*'])->group(function () {
        // Tags
        Route::get('/tags/list', TagsController::class);

        // Manifests
        Route::get('/manifests/{reference}', [ManifestController::class, 'show'])
            ->where('reference', '[a-zA-Z0-9_][a-zA-Z0-9._-]{0,127}|sha256:[a-f0-9]{64}');
        Route::match(['HEAD'], '/manifests/{reference}', [ManifestController::class, 'head'])
            ->where('reference', '[a-zA-Z0-9_][a-zA-Z0-9._-]{0,127}|sha256:[a-f0-9]{64}');
        Route::put('/manifests/{reference}', [ManifestController::class, 'store'])
            ->where('reference', '[a-zA-Z0-9_][a-zA-Z0-9._-]{0,127}|sha256:[a-f0-9]{64}');
        Route::delete('/manifests/{reference}', [ManifestController::class, 'destroy'])
            ->where('reference', '[a-zA-Z0-9_][a-zA-Z0-9._-]{0,127}|sha256:[a-f0-9]{64}');

        // Blobs
        Route::get('/blobs/{digest}', [BlobController::class, 'show'])
            ->where('digest', 'sha256:[a-f0-9]{64}');
        Route::match(['HEAD'], '/blobs/{digest}', [BlobController::class, 'head'])
            ->where('digest', 'sha256:[a-f0-9]{64}');
        Route::delete('/blobs/{digest}', [BlobController::class, 'destroy'])
            ->where('digest', 'sha256:[a-f0-9]{64}');

        // Blob uploads
        Route::post('/blobs/uploads', [UploadController::class, 'start']);
        Route::get('/blobs/uploads/{uuid}', [UploadController::class, 'status']);
        Route::patch('/blobs/uploads/{uuid}', [UploadController::class, 'chunk']);
        Route::put('/blobs/uploads/{uuid}', [UploadController::class, 'complete']);
        Route::delete('/blobs/uploads/{uuid}', [UploadController::class, 'cancel']);
    });
});
