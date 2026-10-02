<?php

use Illuminate\Support\Facades\Route;
use RadThemes\RadpackCrm\Http\Controllers\AutomationsController;
use RadThemes\RadpackCrm\Http\Controllers\CalendarController;
use RadThemes\RadpackCrm\Http\Controllers\CampaignsController;
use RadThemes\RadpackCrm\Http\Controllers\CompaniesController;
use RadThemes\RadpackCrm\Http\Controllers\CompanyActionController;
use RadThemes\RadpackCrm\Http\Controllers\ContactActionController;
use RadThemes\RadpackCrm\Http\Controllers\ContactsController;
use RadThemes\RadpackCrm\Http\Controllers\DashboardController;
use RadThemes\RadpackCrm\Http\Controllers\DeveloperController;
use RadThemes\RadpackCrm\Http\Controllers\EmailsController;
use RadThemes\RadpackCrm\Http\Controllers\EmailTemplatesController;
use RadThemes\RadpackCrm\Http\Controllers\ImportExportController;
use RadThemes\RadpackCrm\Http\Controllers\InvoicesController;
use RadThemes\RadpackCrm\Http\Controllers\NotesController;
use RadThemes\RadpackCrm\Http\Controllers\QuotesController;
use RadThemes\RadpackCrm\Http\Controllers\ReportsController;
use RadThemes\RadpackCrm\Http\Controllers\SegmentsController;
use RadThemes\RadpackCrm\Http\Controllers\TaskActionController;
use RadThemes\RadpackCrm\Http\Controllers\TasksController;
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

    Route::get('calendar', CalendarController::class)->name('calendar');
    Route::get('tasks/json', [TasksController::class, 'json'])->name('tasks.json');
    Route::post('tasks/actions', [TaskActionController::class, 'run'])->name('tasks.actions.run');
    Route::post('tasks/actions/list', [TaskActionController::class, 'bulkActions'])->name('tasks.actions.bulk');
    Route::post('tasks/{task}/toggle', [TasksController::class, 'toggle'])->name('tasks.toggle');
    Route::resource('tasks', TasksController::class)->except('show');

    Route::get('transactions/json', [TransactionsController::class, 'json'])->name('transactions.json');
    Route::resource('transactions', TransactionsController::class)->except('show');

    Route::post('notes/{type}/{id}', [NotesController::class, 'store'])->whereIn('type', ['contact', 'company'])->whereNumber('id')->name('notes.store');
    Route::post('contacts/{contact}/emails', [EmailsController::class, 'store'])->name('contacts.emails.store');
    Route::post('emails/{email}/cancel', [EmailsController::class, 'cancel'])->name('emails.cancel');
    Route::resource('email-templates', EmailTemplatesController::class)->except('show');

    Route::post('segments/preview', [SegmentsController::class, 'preview'])->name('segments.preview');
    Route::post('segments/{segment}/tag', [SegmentsController::class, 'tag'])->name('segments.tag');
    Route::resource('segments', SegmentsController::class)->except('show');

    Route::post('campaigns/{campaign}/test', [CampaignsController::class, 'test'])->name('campaigns.test');
    Route::post('campaigns/{campaign}/send', [CampaignsController::class, 'send'])->name('campaigns.send');
    Route::post('campaigns/{campaign}/cancel', [CampaignsController::class, 'cancel'])->name('campaigns.cancel');
    Route::resource('campaigns', CampaignsController::class);

    Route::post('automations/{automation}/toggle', [AutomationsController::class, 'toggle'])->name('automations.toggle');
    Route::resource('automations', AutomationsController::class)->except('show');
    Route::get('reports', ReportsController::class)->name('reports');

    Route::get('import', [ImportExportController::class, 'create'])->name('import.create');
    Route::post('import', [ImportExportController::class, 'upload'])->name('import.upload');
    Route::get('import/{token}', [ImportExportController::class, 'map'])->name('import.map');
    Route::post('import/{token}', [ImportExportController::class, 'run'])->name('import.run');
    Route::get('export/{type}', [ImportExportController::class, 'export'])->whereIn('type', ['contacts', 'companies'])->name('export');

    Route::get('developer', [DeveloperController::class, 'index'])->name('developer');
    Route::post('developer/keys', [DeveloperController::class, 'storeKey'])->name('developer.keys.store');
    Route::delete('developer/keys/{key}', [DeveloperController::class, 'destroyKey'])->name('developer.keys.destroy');
    Route::post('developer/webhooks', [DeveloperController::class, 'storeWebhook'])->name('developer.webhooks.store');
    Route::patch('developer/webhooks/{webhook}', [DeveloperController::class, 'updateWebhook'])->name('developer.webhooks.update');
    Route::delete('developer/webhooks/{webhook}', [DeveloperController::class, 'destroyWebhook'])->name('developer.webhooks.destroy');
    Route::post('developer/webhooks/{webhook}/test', [DeveloperController::class, 'testWebhook'])->name('developer.webhooks.test');

    Route::delete('notes/{note}', [NotesController::class, 'destroy'])->name('notes.destroy');
});
