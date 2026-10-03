<?php

namespace RadThemes\AlpCrm\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RadThemes\AlpCrm\Models\Webhook;
use RadThemes\AlpCrm\Support\SafeUrl;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(public int $webhookId, public array $body) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 600];
    }

    public function handle(): void
    {
        $webhook = Webhook::find($this->webhookId);

        if (! $webhook || ! $webhook->active) {
            return;
        }

        if (! SafeUrl::allowed($webhook->url)) {
            $webhook->forceFill(['last_status' => null, 'last_error' => __('Blocked: the URL points to a private or local address.'), 'last_sent_at' => now()])->save();

            return;
        }

        $json = json_encode($this->body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'AlpCRM-Webhooks/1.0',
                    'X-Alp-Event' => $this->body['event'],
                    'X-Alp-Delivery' => $this->body['id'],
                    'X-Alp-Signature' => $webhook->sign($json),
                ])
                ->withBody($json, 'application/json')
                ->post($webhook->url);

            $webhook->forceFill([
                'last_status' => $response->status(),
                'last_error' => $response->successful() ? null : Str::limit($response->body(), 500),
                'last_sent_at' => now(),
            ])->save();

            // A REST hook subscriber (e.g. Zapier) that answers 410 Gone has unsubscribed.
            if ($response->status() === 410 && $webhook->source === 'api') {
                $webhook->delete();
            }
        } catch (Throwable $e) {
            $webhook->forceFill(['last_status' => null, 'last_error' => Str::limit($e->getMessage(), 500), 'last_sent_at' => now()])->save();
        }
    }
}
