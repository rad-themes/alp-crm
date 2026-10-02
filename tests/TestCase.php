<?php

namespace RadThemes\RadpackCrm\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use RadThemes\RadpackCrm\ServiceProvider;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\Addon;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\FakesRoles;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use FakesRoles, PreventsSavingStacheItemsToDisk, RefreshDatabase;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Addon settings are saved to a file; start every test from the defaults.
        Addon::get('rad-themes/radpack-crm')->settings()->delete();

        $this->setTestRoles([
            'crm_viewer' => ['access cp', 'view crm'],
            'crm_editor' => ['access cp', 'view crm', 'edit crm'],
            'crm_admin' => ['access cp', 'view crm', 'edit crm', 'delete crm'],
            'no_crm' => ['access cp'],
        ]);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.editions.pro', true);
    }

    protected function admin(): UserContract
    {
        return $this->makeUser('admin@example.com', 'crm_admin');
    }

    protected function makeUser(string $email, ?string $role = null): UserContract
    {
        $user = User::make()->email($email)->data(['name' => ucfirst(strtok($email, '@'))]);

        if ($role) {
            $user->assignRole($role);
        }

        return tap($user)->save();
    }

    /**
     * Send a request as a Control Panel publish form would.
     *
     * @param  array<string, mixed>  $values
     */
    protected function submitForm(string $method, string $url, array $values): TestResponse
    {
        return $this->{$method.'Json'}($url, $values);
    }
}
