<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use RadThemes\RadpackCrm\Http\Controllers\PortalFilesController;
use RadThemes\RadpackCrm\Http\Controllers\PublicDocumentsController;
use RadThemes\RadpackCrm\Http\Controllers\TrackingController;

// Mounted at /!/radpack-crm by Statamic. Documents are looked up by an unguessable token.
Route::name('radpack-crm.public.')->middleware('throttle:60,1')->group(function () {
    Route::get('invoices/{token}', [PublicDocumentsController::class, 'invoice'])->name('invoice');
    Route::get('invoices/{token}/pdf', [PublicDocumentsController::class, 'invoicePdf'])->name('invoice.pdf');
    Route::get('quotes/{token}', [PublicDocumentsController::class, 'quote'])->name('quote');
    Route::get('quotes/{token}/pdf', [PublicDocumentsController::class, 'quotePdf'])->name('quote.pdf');
    Route::post('quotes/{token}/respond', [PublicDocumentsController::class, 'respond'])->middleware('throttle:10,1')->name('quote.respond');
});

Route::name('radpack-crm.')->group(function () {
    Route::get('portal/files/{file}', PortalFilesController::class)->whereNumber('file')->middleware('throttle:60,1')->name('portal.file');
    Route::get('t/{token}/open.gif', [TrackingController::class, 'open'])->name('track.open');
    Route::get('t/{token}/click', [TrackingController::class, 'click'])->middleware('throttle:120,1')->name('track.click');
    Route::get('unsubscribe/{token}', [TrackingController::class, 'confirm'])->middleware('throttle:30,1')->name('unsubscribe');
    // No CSRF: mail clients post here for one-click unsubscribe (RFC 8058).
    Route::post('unsubscribe/{token}', [TrackingController::class, 'unsubscribe'])
        ->middleware('throttle:30,1')
        ->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class])
        ->name('unsubscribe.confirm');
});
