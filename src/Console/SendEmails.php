<?php

namespace RadThemes\AlpCrm\Console;

use Illuminate\Console\Command;
use RadThemes\AlpCrm\Email\CampaignSender;

class SendEmails extends Command
{
    protected $signature = 'alp-crm:send-emails';

    protected $description = 'Send scheduled CRM emails and the next batch of each running campaign';

    public function handle(): int
    {
        $sent = CampaignSender::tick();

        $this->info("Sent {$sent['emails']} scheduled email(s) and {$sent['campaigns']} campaign email(s).");

        return self::SUCCESS;
    }
}
