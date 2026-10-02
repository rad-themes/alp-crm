<?php

namespace RadThemes\RadpackCrm\Console;

use Illuminate\Console\Command;
use RadThemes\RadpackCrm\Email\CampaignSender;

class SendEmails extends Command
{
    protected $signature = 'radpack-crm:send-emails';

    protected $description = 'Send scheduled CRM emails and the next batch of each running campaign';

    public function handle(): int
    {
        $sent = CampaignSender::tick();

        $this->info("Sent {$sent['emails']} scheduled email(s) and {$sent['campaigns']} campaign email(s).");

        return self::SUCCESS;
    }
}
