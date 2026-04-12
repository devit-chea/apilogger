<?php

use Illuminate\Support\Facades\Route;
use GDI\ApiLogger\Http\Controllers\ApiLoggerController;

$prefix     = config('apilogger.route.prefix', 'apilogger');
$middleware = config('apilogger.route.middleware', ['web']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('apilogger.')
    ->group(function () {
        Route::get('/',         [ApiLoggerController::class, 'index'])->name('index');
        Route::get('/{id}',     [ApiLoggerController::class, 'show'])->name('show');
        Route::post('/clear',   [ApiLoggerController::class, 'clear'])->name('clear');
    });
