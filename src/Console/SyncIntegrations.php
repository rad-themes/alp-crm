<?php

namespace RadThemes\AlpCrm\Console;

use Illuminate\Console\Command;
use RadThemes\AlpCrm\Integrations\Sync;
use RadThemes\AlpCrm\Payments\Payments;
use RadThemes\AlpCrm\Support\Settings;
use Throwable;

class SyncIntegrations extends Command
{
    protected $signature = 'alp-crm:sync {service? : stripe, paypal, lists or google (default: every scheduled sync that is turned on)}';

    protected $description = 'Import payments from Stripe/PayPal, contacts from Google, or push contacts to mailing lists';

    public function handle(): int
    {
        $services = $this->argument('service') ? [$this->argument('service')] : array_filter(
            ['stripe', 'paypal', 'google'],
            fn ($service) => $service === 'google' ? Settings::get('google_sync', false) : Payments::enabledForSync($service),
        );

        foreach ($services as $service) {
            if (! Sync::available($service)) {
                $this->warn("{$service}: not set up");

                continue;
            }

            try {
                $this->info(Sync::run($service));
            } catch (Throwable $e) {
                report($e);
                $this->error("{$service}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
