<?php

namespace RadThemes\AlpCrm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * POSTs CRM events to a URL, signed with HMAC-SHA256 (header X-Alp-Signature: sha256=<hex>).
 *
 * @property int $id
 * @property string $name
 * @property string $url
 * @property array<int, string> $events
 * @property string $secret
 * @property bool $active
 * @property string $source cp|api (REST hook subscriptions, e.g. Zapier)
 */
class Webhook extends Model
{
    protected $table = 'crm_webhooks';

    protected $guarded = ['id'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['events' => 'array', 'active' => 'boolean', 'last_sent_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Webhook $webhook) {
            $webhook->secret ??= Str::random(40);
        });
    }

    public function listensTo(string $event): bool
    {
        return $this->active && (in_array('*', (array) $this->events, true) || in_array($event, (array) $this->events, true));
    }

    public function sign(string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $body, $this->secret);
    }
}
