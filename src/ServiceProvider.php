<?php

namespace RadThemes\AlpCrm;

use Illuminate\Console\Scheduling\Schedule;
use RadThemes\AlpCrm\Actions\AddTags;
use RadThemes\AlpCrm\Actions\ChangeStatus;
use RadThemes\AlpCrm\Actions\CompleteTasks;
use RadThemes\AlpCrm\Actions\DeleteRecords;
use RadThemes\AlpCrm\Automations\Actions;
use RadThemes\AlpCrm\Automations\Engine;
use RadThemes\AlpCrm\Automations\SkipAction;
use RadThemes\AlpCrm\Console\RunAutomations;
use RadThemes\AlpCrm\Console\SendEmails;
use RadThemes\AlpCrm\Console\SendTaskReminders;
use RadThemes\AlpCrm\Console\SyncIntegrations;
use RadThemes\AlpCrm\Email\MergeTags;
use RadThemes\AlpCrm\Events\CrmEvent;
use RadThemes\AlpCrm\Fieldtypes\CrmCompanies;
use RadThemes\AlpCrm\Fieldtypes\CrmContacts;
use RadThemes\AlpCrm\Integrations\Lists\ListSync;
use RadThemes\AlpCrm\Integrations\Twilio;
use RadThemes\AlpCrm\Listeners\CaptureFormSubmission;
use RadThemes\AlpCrm\Listeners\CaptureRegisteredUser;
use RadThemes\AlpCrm\Listeners\SendWebhooks;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Portal\PortalPages;
use RadThemes\AlpCrm\Scopes\CrmSegment;
use RadThemes\AlpCrm\Scopes\CrmStatus;
use RadThemes\AlpCrm\Scopes\CrmTag;
use RadThemes\AlpCrm\Support\MorphMap;
use RadThemes\AlpCrm\Support\Settings;
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
        CrmEvent::class => [SendWebhooks::class, Engine::class, ListSync::class],
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
        SyncIntegrations::class,
        SendTaskReminders::class,
    ];

    public function bootAddon(): void
    {
        MorphMap::register();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Permission::extend(function () {
            Permission::group('alp_crm', 'Alp CRM', function () {
                Permission::register('manage crm passwords')->label(__('Manage client passwords'))->description(__('See, add and change saved client passwords.'));
                Permission::register('view crm', function ($permission) {
                    $permission->label(__('View CRM'))->children([
                        Permission::make('edit crm')->label(__('Create and edit CRM records')),
                        Permission::make('delete crm')->label(__('Delete CRM records')),
                    ]);
                });
            });
        });

        PortalPages::register();

        Actions::extend('send_sms', __('Send a text message (Twilio)'), function (array $action, ?Contact $contact) {
            if (! $contact || ! $contact->phone || ! Twilio::configured()) {
                throw new SkipAction($contact?->phone ? __('Twilio isn’t set up') : __('No phone number'));
            }

            Twilio::send($contact, MergeTags::render((string) ($action['text'] ?? ''), MergeTags::for($contact)));

            return __('Texted :phone', ['phone' => $contact->phone]);
        });

        Nav::extend(function ($nav) {
            $section = Settings::get('crm_name') ?: 'CRM';

            // Statamic lists addon sections last, below Users; this link keeps the CRM in view at the top.
            $nav->create($section)->section('Top Level')->route('alp-crm.dashboard')->icon('users')->can('view crm');

            $nav->create(__('Dashboard'))->section($section)->route('alp-crm.dashboard')->icon('dashboard')->can('view crm');
            $nav->create(__('Contacts'))->section($section)->route('alp-crm.contacts.index')->icon('users')->can('view crm')->children([
                Nav::item(__('Segments'))->route('alp-crm.segments.index')->can('view crm'),
                Nav::item(__('Import'))->route('alp-crm.import.create')->can('edit crm'),
            ]);
            $nav->create(__('Companies'))->section($section)->route('alp-crm.companies.index')->icon('building-generic')->can('view crm');
            $nav->create(__('Tasks'))->section($section)->route('alp-crm.tasks.index')->icon('checkbox')->can('view crm')->children([
                Nav::item(__('Calendar'))->route('alp-crm.calendar')->can('view crm'),
            ]);
            $nav->create(__('Quotes'))->section($section)->route('alp-crm.quotes.index')->icon('file-content-list')->can('view crm');
            $nav->create(__('Invoices'))->section($section)->route('alp-crm.invoices.index')->icon('money-cashier-price-tag')->can('view crm');
            $nav->create(__('Transactions'))->section($section)->route('alp-crm.transactions.index')->icon('money-cash-bill')->can('view crm');
            $nav->create(__('Campaigns'))->section($section)->route('alp-crm.campaigns.index')->icon('mail-send-email-attachment-document')->can('view crm')->children([
                Nav::item(__('Email templates'))->route('alp-crm.email-templates.index')->can('view crm'),
            ]);
            $nav->create(__('Automations'))->section($section)->route('alp-crm.automations.index')->icon('flash-bolt-lightning')->can('view crm');
            $nav->create(__('Reports'))->section($section)->route('alp-crm.reports')->icon('chart-monitoring-indicator')->can('view crm');
            $nav->create(__('Settings'))->section($section)->url(Addon::get('rad-themes/alp-crm')->settingsUrl())->icon('cog')->can('configure addons')->children([
                Nav::item(__('Integrations'))->route('alp-crm.integrations')->can('configure addons'),
                Nav::item(__('API & webhooks'))->route('alp-crm.developer')->can('configure addons'),
            ]);
        });
    }

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('alp-crm:task-reminders')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('alp-crm:send-emails')->everyMinute()->withoutOverlapping();
        $schedule->command('alp-crm:automations')->everyMinute()->withoutOverlapping();
        $schedule->command('alp-crm:sync')->hourly()->withoutOverlapping();
    }
}
