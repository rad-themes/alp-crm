<?php

use Illuminate\Support\Facades\Route;
use RadThemes\RadpackCrm\Http\Controllers\PublicDocumentsController;

// Mounted at /!/radpack-crm by Statamic. Documents are looked up by an unguessable token.
Route::name('radpack-crm.public.')->middleware('throttle:60,1')->group(function () {
    Route::get('invoices/{token}', [PublicDocumentsController::class, 'invoice'])->name('invoice');
    Route::get('invoices/{token}/pdf', [PublicDocumentsController::class, 'invoicePdf'])->name('invoice.pdf');
    Route::get('quotes/{token}', [PublicDocumentsController::class, 'quote'])->name('quote');
    Route::get('quotes/{token}/pdf', [PublicDocumentsController::class, 'quotePdf'])->name('quote.pdf');
    Route::post('quotes/{token}/respond', [PublicDocumentsController::class, 'respond'])->middleware('throttle:10,1')->name('quote.respond');
});
