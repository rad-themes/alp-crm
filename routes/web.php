<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use RadThemes\AlpCrm\Http\Controllers\Api;
use RadThemes\AlpCrm\Http\Middleware\AuthenticateApiKey;
use Statamic\StaticCaching\Middleware\Cache;

/*
 * REST API: /api/alp-crm/v1/... authenticated with an API key (CRM → API & webhooks).
 * Registered as a web route so it comes before Statamic's front-end catch-all; CSRF doesn't apply to key auth.
 */
Route::prefix('api/alp-crm/v1')
    ->name('alp-crm.api.')
    ->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class, Cache::class])
    ->middleware([AuthenticateApiKey::class, 'throttle:120,1'])
    ->group(function () {
        Route::get('me', [Api\MiscController::class, 'me'])->name('me');
        Route::get('events', [Api\MiscController::class, 'events'])->name('events');
        Route::post('hooks', [Api\MiscController::class, 'subscribe'])->name('hooks.subscribe');
        Route::delete('hooks/{id}', [Api\MiscController::class, 'unsubscribe'])->whereNumber('id')->name('hooks.unsubscribe');

        Route::post('contacts/upsert', [Api\ContactsController::class, 'upsert'])->name('contacts.upsert');
        Route::post('contacts/{id}/notes', [Api\MiscController::class, 'storeNote'])->whereNumber('id')->name('contacts.notes');
        Route::match(['post', 'delete'], 'contacts/{id}/tags', [Api\ContactsController::class, 'tag'])->whereNumber('id')->name('contacts.tags');

        foreach (['contacts' => Api\ContactsController::class, 'companies' => Api\CompaniesController::class, 'tasks' => Api\TasksController::class, 'transactions' => Api\TransactionsController::class] as $resource => $controller) {
            Route::get($resource, [$controller, 'index'])->name("{$resource}.index");
            Route::post($resource, [$controller, 'store'])->name("{$resource}.store");
            Route::get("{$resource}/{id}", [$controller, 'show'])->whereNumber('id')->name("{$resource}.show");
            Route::match(['put', 'patch'], "{$resource}/{id}", [$controller, 'update'])->whereNumber('id')->name("{$resource}.update");
            Route::delete("{$resource}/{id}", [$controller, 'destroy'])->whereNumber('id')->name("{$resource}.destroy");
        }

        Route::get('{type}', [Api\DocumentsController::class, 'index'])->whereIn('type', ['quotes', 'invoices'])->name('documents.index');
        Route::get('{type}/{id}', [Api\DocumentsController::class, 'show'])->whereIn('type', ['quotes', 'invoices'])->whereNumber('id')->name('documents.show');
    });
