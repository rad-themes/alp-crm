<?php

namespace RadThemes\RadpackCrm;

use Illuminate\Console\Scheduling\Schedule;
use RadThemes\RadpackCrm\Actions\AddTags;
use RadThemes\RadpackCrm\Actions\ChangeStatus;
use RadThemes\RadpackCrm\Actions\CompleteTasks;
use RadThemes\RadpackCrm\Actions\DeleteRecords;
use RadThemes\RadpackCrm\Automations\Engine;
use RadThemes\RadpackCrm\Console\RunAutomations;
use RadThemes\RadpackCrm\Console\SendEmails;
use RadThemes\RadpackCrm\Console\SendTaskReminders;
use RadThemes\RadpackCrm\Events\CrmEvent;
use RadThemes\RadpackCrm\Fieldtypes\CrmCompanies;
use RadThemes\RadpackCrm\Fieldtypes\CrmContacts;
use RadThemes\RadpackCrm\Listeners\CaptureFormSubmission;
use RadThemes\RadpackCrm\Listeners\CaptureRegisteredUser;
use RadThemes\RadpackCrm\Listeners\SendWebhooks;
use RadThemes\RadpackCrm\Scopes\CrmSegment;
use RadThemes\RadpackCrm\Scopes\CrmStatus;
use RadThemes\RadpackCrm\Scopes\CrmTag;
use Statamic\Events\SubmissionCreated;
use Statamic\Events\UserRegistered;
use Statamic\Facades\Addon;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => ['resources/css/cp.css', 'resources/js/cp.js'],
        'publicDirectory' => 'resources/dist',
    ];

    protected $listen = [
        CrmEvent::class => [SendWebhooks::class, Engine::class],
        SubmissionCreated::class => [CaptureFormSubmission::class],
        UserRegistered::class => [CaptureRegisteredUser::class],
    ];

    protected $fieldtypes = [
        CrmCompanies::class,
        CrmContacts::class,
    ];

    protected $scopes = [
        CrmSegment::class,
        CrmStatus::class,
        CrmTag::class,
    ];

    protected $actions = [
        AddTags::class,
        ChangeStatus::class,
        CompleteTasks::class,
        DeleteRecords::class,
    ];

    protected $commands = [
        RunAutomations::class,
        SendEmails::class,
        SendTaskReminders::class,
    ];

    public function bootAddon(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Permission::extend(function () {
            Permission::group('radpack_crm', 'Radpack CRM', function () {
                Permission::register('view crm', function ($permission) {
                    $permission->label(__('View CRM'))->children([
                        Permission::make('edit crm')->label(__('Create and edit CRM records')),
                        Permission::make('delete crm')->label(__('Delete CRM records')),
                    ]);
                });
            });
        });

        Nav::extend(function ($nav) {
            $nav->create(__('Dashboard'))->section('CRM')->route('radpack-crm.dashboard')->icon('dashboard')->can('view crm');
            $nav->create(__('Contacts'))->section('CRM')->route('radpack-crm.contacts.index')->icon('users')->can('view crm');
            $nav->create(__('Companies'))->section('CRM')->route('radpack-crm.companies.index')->icon('building-generic')->can('view crm');
            $nav->create(__('Tasks'))->section('CRM')->route('radpack-crm.tasks.index')->icon('checkbox')->can('view crm');
            $nav->create(__('Calendar'))->section('CRM')->route('radpack-crm.calendar')->icon('calendar')->can('view crm');
            $nav->create(__('Quotes'))->section('CRM')->route('radpack-crm.quotes.index')->icon('file-content-list')->can('view crm');
            $nav->create(__('Invoices'))->section('CRM')->route('radpack-crm.invoices.index')->icon('money-cashier-price-tag')->can('view crm');
            $nav->create(__('Transactions'))->section('CRM')->route('radpack-crm.transactions.index')->icon('money-cash-bill')->can('view crm');
            $nav->create(__('Segments'))->section('CRM')->route('radpack-crm.segments.index')->icon('filter')->can('view crm');
            $nav->create(__('Campaigns'))->section('CRM')->route('radpack-crm.campaigns.index')->icon('mail-send-email-attachment-document')->can('view crm');
            $nav->create(__('Email templates'))->section('CRM')->route('radpack-crm.email-templates.index')->icon('mail-chat-bubble-text')->can('view crm');
            $nav->create(__('Automations'))->section('CRM')->route('radpack-crm.automations.index')->icon('flash-bolt-lightning')->can('view crm');
            $nav->create(__('Reports'))->section('CRM')->route('radpack-crm.reports')->icon('chart-monitoring-indicator')->can('view crm');
            $nav->create(__('API & webhooks'))->section('CRM')->route('radpack-crm.developer')->icon('git')->can('configure addons');
            $nav->create(__('Settings'))->section('CRM')->url(Addon::get('rad-themes/radpack-crm')->settingsUrl())->icon('cog')->can('configure addons');
        });
    }

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('radpack-crm:task-reminders')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('radpack-crm:send-emails')->everyMinute()->withoutOverlapping();
        $schedule->command('radpack-crm:automations')->everyMinute()->withoutOverlapping();
    }
}
