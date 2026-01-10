<?php

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
Route::middleware('packgrid.token')->group(function () {
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
Route::prefix('npm')->middleware('packgrid.token')->group(function () {
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
