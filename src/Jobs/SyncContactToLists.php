<?php

namespace RadThemes\RadpackCrm\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RadThemes\RadpackCrm\Integrations\Lists\ListSync;
use RadThemes\RadpackCrm\Models\Contact;
use Throwable;

class SyncContactToLists implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $uniqueFor = 60;

    public function __construct(public int $contactId) {}

    public function uniqueId(): string
    {
        return (string) $this->contactId;
    }

    public function handle(): void
    {
        $contact = Contact::find($this->contactId);

        if (! $contact || ! ListSync::shouldSync($contact)) {
            return;
        }

        foreach (ListSync::active() as $key => $service) {
            try {
                $service::sync($contact);
            } catch (Throwable $e) {
                report($e);
                $contact->logActivity('list_sync_failed', __(':service sync failed: :error', ['service' => $service::label(), 'error' => str($e->getMessage())->limit(200)]));
            }
        }
    }
}
