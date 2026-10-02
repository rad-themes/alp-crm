<?php

namespace RadThemes\RadpackCrm;

use RadThemes\RadpackCrm\Actions\AddTags;
use RadThemes\RadpackCrm\Actions\ChangeStatus;
use RadThemes\RadpackCrm\Actions\DeleteRecords;
use RadThemes\RadpackCrm\Fieldtypes\CrmCompanies;
use RadThemes\RadpackCrm\Scopes\CrmStatus;
use RadThemes\RadpackCrm\Scopes\CrmTag;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => ['resources/css/cp.css', 'resources/js/cp.js'],
        'publicDirectory' => 'resources/dist',
    ];

    protected $fieldtypes = [
        CrmCompanies::class,
    ];

    protected $scopes = [
        CrmStatus::class,
        CrmTag::class,
    ];

    protected $actions = [
        AddTags::class,
        ChangeStatus::class,
        DeleteRecords::class,
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
        });
    }
}
