<?php

namespace RadThemes\RadpackCrm\Listeners;

use Illuminate\Support\Str;
use RadThemes\RadpackCrm\Events\CrmEvent;
use RadThemes\RadpackCrm\Jobs\DeliverWebhook;
use RadThemes\RadpackCrm\Models\Webhook;

class SendWebhooks
{
    public function handle(CrmEvent $event): void
    {
        $webhooks = Webhook::where('active', true)->get()->filter->listensTo($event->name);

        foreach ($webhooks as $webhook) {
            $body = [
                'id' => (string) Str::uuid(),
                'event' => $event->name,
                'created_at' => now()->toIso8601String(),
                'data' => $event->payload,
                'context' => (object) $event->context,
            ];

            // With the sync queue, deliver after the response so a slow endpoint doesn't slow the CP down.
            config('queue.default') === 'sync'
                ? DeliverWebhook::dispatchAfterResponse($webhook->id, $body)
                : DeliverWebhook::dispatch($webhook->id, $body);
        }
    }
}
