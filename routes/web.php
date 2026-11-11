<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\CategoryManagementController;
use App\Http\Controllers\Admin\CulturalCurationController;
use App\Http\Controllers\Admin\CulturalEntryManagementController;
use App\Http\Controllers\Admin\MediaSourceManagementController;
use App\Http\Controllers\Admin\RegionManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\CulturalEntryController;
use App\Http\Controllers\CulturalMapController;
use App\Http\Controllers\CulturalMediaController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\DigitalBookController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RegionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::view('/tentang-kami', 'about')->name('about');
Route::get('/cerita-nusantara', [DigitalBookController::class, 'index'])
    ->name('digital-books.index');
Route::get('/peta-budaya', CulturalMapController::class)
    ->name('cultural-map.index');
Route::get('/cerita-nusantara/{slug}', [DigitalBookController::class, 'show'])
    ->name('digital-books.show');
Route::get('/jelajah-nusantara', [RegionController::class, 'index'])
    ->name('regions.index');
Route::get('/jelajah-nusantara/{slug}', [RegionController::class, 'show'])
    ->name('regions.show');
Route::get('/wisata/{slug}', [DestinationController::class, 'show'])
    ->name('destinations.show');
Route::get('/arsip-budaya', [CulturalEntryController::class, 'index'])
    ->name('cultural-entries.index');
Route::get('/arsip-budaya/{slug}', [CulturalEntryController::class, 'show'])
    ->name('cultural-entries.show');
Route::get('/cultural-media/{media}', [CulturalMediaController::class, 'show'])
    ->whereNumber('media')
    ->name('cultural-media.show');
Route::post('/ai-sahabat-budaya/chat', AiChatController::class)
    ->middleware('throttle:10,1')
    ->name('ai.chat');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'create'])
        ->name('login');
    Route::post('/login', [AdminAuthController::class, 'store'])
        ->middleware('throttle:admin-login')
        ->name('login.store');
    Route::get('/super-access', fn () => redirect()->route('admin.home'))
        ->middleware(['auth', 'can:manage-admin-accounts'])
        ->name('super-entry');
    Route::view('/cultural-entries/manage', 'admin.cultural-entries.index')
        ->middleware(['auth', 'can:manage-cultural-content'])
        ->name('cultural-entries.manage');
    Route::view('/users/manage', 'admin.users.index')
        ->middleware(['auth', 'can:manage-admin-accounts'])
        ->name('users.page');
    Route::view('/analytics', 'admin.analytics.index')
        ->middleware(['auth', 'can:manage-admin-accounts'])
        ->name('analytics.page');

    Route::middleware('admin.access')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'destroy'])
            ->name('logout');
        Route::get('/', [CulturalCurationController::class, 'index'])
            ->name('home');
        Route::view('/media-sources/manage', 'admin.media-sources.index')
            ->name('media-sources.page');
        Route::post('/cultural-entries/publication/bulk', [CulturalCurationController::class, 'publishEligible'])
            ->middleware('can:manage-cultural-content')
            ->name('curation.entries.publication.bulk');
        Route::get('/cultural-entries/{entry}', [CulturalCurationController::class, 'show'])
            ->name('curation.entries.show');
        Route::get('/cultural-entries/{entry}/media/{media}/preview', [CulturalMediaController::class, 'preview'])
            ->whereNumber(['entry', 'media'])
            ->middleware('can:manage-media')
            ->name('curation.media.preview');
        Route::post('/cultural-media/permission/bulk', [CulturalCurationController::class, 'grantPendingMedia'])
            ->middleware('can:manage-media')
            ->name('curation.media.permission.bulk');
        Route::post('/cultural-entries/{entry}/verification', [CulturalCurationController::class, 'verify'])
            ->middleware('can:verify-content')
            ->name('curation.entries.verification');
        Route::post('/cultural-entries/{entry}/publication', [CulturalCurationController::class, 'publish'])
            ->middleware('can:manage-cultural-content')
            ->name('curation.entries.publication');
        Route::post('/cultural-entries/{entry}/media/{media}/permission', [CulturalCurationController::class, 'reviewMedia'])
            ->middleware('can:manage-media')
            ->name('curation.media.permission');
    });

    Route::prefix('regions')
        ->name('regions.')
        ->middleware(['auth', 'can:manage-regions'])
        ->controller(RegionManagementController::class)
        ->group(function () {
            Route::get('/manage', 'page')->name('page');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{region}', 'show')->name('show');
            Route::put('/{region}', 'update')->name('update');
            Route::patch('/{region}/status', 'setStatus')->name('status');
            Route::delete('/{region}', 'destroy')->name('destroy');
        });

    Route::prefix('categories')
        ->name('categories.')
        ->middleware(['auth', 'can:manage-categories'])
        ->controller(CategoryManagementController::class)
        ->group(function () {
            Route::get('/manage', 'page')->name('page');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{category}', 'show')->name('show');
            Route::put('/{category}', 'update')->name('update');
            Route::patch('/{category}/status', 'setStatus')->name('status');
            Route::delete('/{category}', 'destroy')->name('destroy');
        });

    Route::prefix('api/cultural-entries')
        ->name('cultural-entries.api.')
        ->middleware(['auth', 'can:manage-cultural-content'])
        ->controller(CulturalEntryManagementController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('/with-media', 'storeWithMedia')
                ->middleware('can:manage-media')
                ->name('store-with-media');
            Route::get('/{entry}', 'show')->whereNumber('entry')->name('show');
            Route::put('/{entry}', 'update')->whereNumber('entry')->name('update');
            Route::delete('/{entry}', 'destroy')->whereNumber('entry')->name('destroy');
        });

    Route::prefix('api')
        ->name('api.')
        ->middleware('auth')
        ->controller(MediaSourceManagementController::class)
        ->group(function () {
            Route::prefix('media')
                ->name('media.')
                ->middleware('can:manage-media')
                ->group(function () {
                    Route::get('/', 'mediaIndex')->name('index');
                    Route::post('/', 'mediaStore')->name('store');
                    Route::get('/{media}', 'mediaShow')->whereNumber('media')->name('show');
                    Route::put('/{media}', 'mediaUpdate')->whereNumber('media')->name('update');
                    Route::delete('/{media}', 'mediaDestroy')->whereNumber('media')->name('destroy');
                });

            Route::prefix('sources')
                ->name('sources.')
                ->middleware('can:manage-sources')
                ->group(function () {
                    Route::get('/', 'sourceIndex')->name('index');
                    Route::post('/', 'sourceStore')->name('store');
                    Route::get('/{source}', 'sourceShow')->whereNumber('source')->name('show');
                    Route::put('/{source}', 'sourceUpdate')->whereNumber('source')->name('update');
                    Route::delete('/{source}', 'sourceDestroy')->whereNumber('source')->name('destroy');
                });
        });

    Route::prefix('api/users')
        ->name('api.users.')
        ->middleware(['auth', 'can:manage-admin-accounts'])
        ->controller(UserManagementController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{user}', 'show')->whereNumber('user')->name('show');
            Route::put('/{user}', 'update')->whereNumber('user')->name('update');
            Route::patch('/{user}/status', 'setStatus')->whereNumber('user')->name('status');
            Route::put('/{user}/password', 'resetPassword')->whereNumber('user')->name('password');
        });

    Route::prefix('api/analytics')
        ->name('api.analytics.')
        ->middleware(['auth', 'can:manage-admin-accounts'])
        ->controller(AnalyticsController::class)
        ->group(function () {
            Route::get('/summary', 'summary')->name('summary');
            Route::get('/traffic', 'traffic')->name('traffic');
            Route::get('/devices', 'devices')->name('devices');
            Route::get('/pages', 'pages')->name('pages');
            Route::get('/geography', 'geography')->name('geography');
        });
});
