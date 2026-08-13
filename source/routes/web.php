<?php

use Illuminate\Support\Facades\Route;
use Yurba\Cmf\Http\Controllers\AuthController;
use Yurba\Cmf\Http\Controllers\DashboardController;
use Yurba\Cmf\Http\Controllers\ImportExportController;
use Yurba\Cmf\Http\Controllers\MediaController;
use Yurba\Cmf\Http\Controllers\PageController;
use Yurba\Cmf\Http\Controllers\ResourceController;
use Yurba\Cmf\Http\Controllers\SearchController;
use Yurba\Cmf\Http\Controllers\SettingsController;
use Yurba\Cmf\Http\Controllers\SitemapController;
use Yurba\Cmf\Http\Controllers\UploadController;

$prefix = trim((string) config('yurba.prefix', 'admin'), '/');
$middleware = (array) config('yurba.middleware', ['web']);

// Public XML sitemap — deliberately outside the admin prefix and auth gate.
if (config('yurba.sitemap.enabled', true)) {
    Route::get('sitemap.xml', [SitemapController::class, 'index'])
        ->middleware('web')->name('yurba.sitemap');
}

Route::prefix($prefix)->middleware($middleware)->name('yurba.')->group(function () {
    // Auth (no gate — the login screen must be reachable by guests).
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Everything below requires an authorized admin.
    Route::middleware('yurba.auth')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Registered before the {resource} catch-all so the literal paths win.
        Route::post('_editor/upload', [UploadController::class, 'store'])->name('editor.upload');
        Route::get('search', [SearchController::class, 'page'])->name('search');
        Route::get('media', [MediaController::class, 'index'])->name('media');
        Route::get('media/list', [MediaController::class, 'list'])->name('media.list');
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::delete('media/{id}', [MediaController::class, 'destroy'])->name('media.destroy');
        Route::get('settings/{page}', [SettingsController::class, 'show'])->name('settings.show');
        Route::put('settings/{page}', [SettingsController::class, 'save'])->name('settings.save');

        // custom screens registered in config('yurba.pages')
        Route::get('pages/{page}', [PageController::class, 'show'])->name('page.show');
        Route::post('pages/{page}', [PageController::class, 'handle'])->name('page.handle');

        Route::get('{resource}', [ResourceController::class, 'index'])->name('resource.index');
        Route::post('{resource}/bulk', [ResourceController::class, 'bulk'])->name('resource.bulk');
        Route::post('{resource}/reorder', [ResourceController::class, 'reorder'])->name('resource.reorder');
        Route::post('{resource}/action', [ResourceController::class, 'action'])->name('resource.action');
        Route::get('{resource}/export', [ImportExportController::class, 'export'])->name('resource.export');
        Route::get('{resource}/import', [ImportExportController::class, 'importForm'])->name('resource.import');
        Route::post('{resource}/import', [ImportExportController::class, 'import'])->name('resource.import.run');
        Route::get('{resource}/create', [ResourceController::class, 'create'])->name('resource.create');
        Route::get('{resource}/{id}', [ResourceController::class, 'show'])->name('resource.show');
        Route::post('{resource}', [ResourceController::class, 'store'])->name('resource.store');
        Route::get('{resource}/{id}/edit', [ResourceController::class, 'edit'])->name('resource.edit');
        Route::put('{resource}/{id}', [ResourceController::class, 'update'])->name('resource.update');
        Route::post('{resource}/{id}/revisions/{revision}/restore', [ResourceController::class, 'restoreRevision'])->name('resource.revision.restore');
        Route::post('{resource}/{id}/restore', [ResourceController::class, 'restore'])->name('resource.restore');
        Route::delete('{resource}/{id}/force', [ResourceController::class, 'forceDelete'])->name('resource.forceDelete');
        Route::delete('{resource}/{id}', [ResourceController::class, 'destroy'])->name('resource.destroy');
    });
});
