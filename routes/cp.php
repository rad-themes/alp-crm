<?php

use Illuminate\Support\Facades\Route;
use RadThemes\RadpackCrm\Http\Controllers\CompaniesController;
use RadThemes\RadpackCrm\Http\Controllers\CompanyActionController;
use RadThemes\RadpackCrm\Http\Controllers\ContactActionController;
use RadThemes\RadpackCrm\Http\Controllers\ContactsController;
use RadThemes\RadpackCrm\Http\Controllers\DashboardController;
use RadThemes\RadpackCrm\Http\Controllers\NotesController;

Route::prefix('crm')->name('radpack-crm.')->group(function () {
    Route::get('/', fn () => redirect()->route('statamic.cp.radpack-crm.dashboard'))->name('home');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('contacts/json', [ContactsController::class, 'json'])->name('contacts.json');
    Route::post('contacts/actions', [ContactActionController::class, 'run'])->name('contacts.actions.run');
    Route::post('contacts/actions/list', [ContactActionController::class, 'bulkActions'])->name('contacts.actions.bulk');
    Route::resource('contacts', ContactsController::class);

    Route::get('companies/json', [CompaniesController::class, 'json'])->name('companies.json');
    Route::post('companies/actions', [CompanyActionController::class, 'run'])->name('companies.actions.run');
    Route::post('companies/actions/list', [CompanyActionController::class, 'bulkActions'])->name('companies.actions.bulk');
    Route::resource('companies', CompaniesController::class);

    Route::post('notes/{type}/{id}', [NotesController::class, 'store'])->whereIn('type', ['contact', 'company'])->whereNumber('id')->name('notes.store');
    Route::delete('notes/{note}', [NotesController::class, 'destroy'])->name('notes.destroy');
});
