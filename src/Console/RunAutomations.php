<?php

namespace RadThemes\AlpCrm\Console;

use Illuminate\Console\Command;
use RadThemes\AlpCrm\Automations\Engine;

class RunAutomations extends Command
{
    protected $signature = 'alp-crm:automations';

    protected $description = 'Run delayed CRM automation steps that are due';

    public function handle(): int
    {
        $this->info('Ran '.Engine::runDue().' automation step(s).');

        return self::SUCCESS;
    }
}
