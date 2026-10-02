<?php

namespace RadThemes\RadpackCrm\Console;

use Illuminate\Console\Command;
use RadThemes\RadpackCrm\Automations\Engine;

class RunAutomations extends Command
{
    protected $signature = 'radpack-crm:automations';

    protected $description = 'Run delayed CRM automation steps that are due';

    public function handle(): int
    {
        $this->info('Ran '.Engine::runDue().' automation step(s).');

        return self::SUCCESS;
    }
}
