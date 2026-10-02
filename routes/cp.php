<?php

use Illuminate\Support\Facades\Route;
use RadThemes\RadpackCrm\Http\Controllers\CompaniesController;
use RadThemes\RadpackCrm\Http\Controllers\CompanyActionController;
use RadThemes\RadpackCrm\Http\Controllers\ContactActionController;
use RadThemes\RadpackCrm\Http\Controllers\ContactsController;
use RadThemes\RadpackCrm\Http\Controllers\DashboardController;
use RadThemes\RadpackCrm\Http\Controllers\InvoicesController;
use RadThemes\RadpackCrm\Http\Controllers\NotesController;
use RadThemes\RadpackCrm\Http\Controllers\QuotesController;
use RadThemes\RadpackCrm\Http\Controllers\TransactionsController;

Route::prefix('crm')->name('radpack-crm.')->group(function () {
    Route::get('/', fn () => redirect()->route('statamic.cp.radpack-crm.dashboard'))->name('home');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('contacts/json', [ContactsController::class, 'json'])->name('contacts.json');
    Route::get('contacts/search', [ContactsController::class, 'search'])->name('contacts.search');
    Route::post('contacts/actions', [ContactActionController::class, 'run'])->name('contacts.actions.run');
    Route::post('contacts/actions/list', [ContactActionController::class, 'bulkActions'])->name('contacts.actions.bulk');
    Route::resource('contacts', ContactsController::class);

    Route::get('companies/json', [CompaniesController::class, 'json'])->name('companies.json');
    Route::post('companies/actions', [CompanyActionController::class, 'run'])->name('companies.actions.run');
    Route::post('companies/actions/list', [CompanyActionController::class, 'bulkActions'])->name('companies.actions.bulk');
    Route::resource('companies', CompaniesController::class);

    foreach (['quotes' => QuotesController::class, 'invoices' => InvoicesController::class] as $plural => $controller) {
        Route::get("{$plural}/json", [$controller, 'json'])->name("{$plural}.json");
        Route::get("{$plural}/{id}/pdf", [$controller, 'pdf'])->whereNumber('id')->name("{$plural}.pdf");
        Route::post("{$plural}/{id}/send", [$controller, 'send'])->whereNumber('id')->name("{$plural}.send");
        Route::post("{$plural}/{id}/mark-sent", [$controller, 'markSent'])->whereNumber('id')->name("{$plural}.mark-sent");
        Route::resource($plural, $controller)->parameters([$plural => 'id'])->whereNumber('id');
    }

    Route::post('invoices/{id}/payments', [InvoicesController::class, 'recordPayment'])->whereNumber('id')->name('invoices.payments.store');
    Route::post('invoices/{id}/void', [InvoicesController::class, 'void'])->whereNumber('id')->name('invoices.void');
    Route::post('quotes/{id}/respond', [QuotesController::class, 'respond'])->whereNumber('id')->name('quotes.respond');
    Route::post('quotes/{id}/convert', [QuotesController::class, 'convert'])->whereNumber('id')->name('quotes.convert');

    Route::get('transactions/json', [TransactionsController::class, 'json'])->name('transactions.json');
    Route::resource('transactions', TransactionsController::class)->except('show');

    Route::post('notes/{type}/{id}', [NotesController::class, 'store'])->whereIn('type', ['contact', 'company'])->whereNumber('id')->name('notes.store');
    Route::delete('notes/{note}', [NotesController::class, 'destroy'])->name('notes.destroy');
});
