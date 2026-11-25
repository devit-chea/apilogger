<?php

use Illuminate\Support\Facades\Route;
use GDI\ApiLogger\Http\Controllers\ApiLoggerController;
use GDI\ApiLogger\Http\Controllers\ApiLoggerRuleController;

$prefix     = config('apilogger.route.prefix', 'apilogger');
$middleware = config('apilogger.route.middleware', ['web']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('apilogger.')
    ->group(function () {
        Route::get('/',             [ApiLoggerController::class, 'index'])->name('index');

        // Storage rules. Registered before /{id} so "rules" is not taken as a log id.
        Route::get('/rules',                [ApiLoggerRuleController::class, 'index'])->name('rules.index');
        Route::get('/rules/create',         [ApiLoggerRuleController::class, 'create'])->name('rules.create');
        Route::post('/rules',               [ApiLoggerRuleController::class, 'store'])->name('rules.store');
        Route::post('/rules/import',        [ApiLoggerRuleController::class, 'import'])->name('rules.import');
        Route::get('/rules/{rule}/edit',    [ApiLoggerRuleController::class, 'edit'])->name('rules.edit');
        Route::put('/rules/{rule}',         [ApiLoggerRuleController::class, 'update'])->name('rules.update');
        Route::delete('/rules/{rule}',      [ApiLoggerRuleController::class, 'destroy'])->name('rules.destroy');

        Route::get('/{id}',         [ApiLoggerController::class, 'show'])->name('show');
        Route::get('/{id}/response', [ApiLoggerController::class, 'response'])->name('response');
        Route::post('/clear',       [ApiLoggerController::class, 'clear'])->name('clear');
    });
