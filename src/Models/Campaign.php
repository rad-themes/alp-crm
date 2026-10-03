<?php

namespace RadThemes\AlpCrm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A marketing email sent to a segment.
 *
 * @property int $id
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property string $status draft|scheduled|sending|sent|cancelled
 */
class Campaign extends Model
{
    protected $table = 'crm_campaigns';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * Contacts this campaign would go to right now: the segment (or everyone), with an email, still subscribed.
     */
    public function audience(): Builder
    {
        return ($this->segment ? $this->segment->contacts() : Contact::query())
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNull('unsubscribed_at');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }

    /**
     * @return array{recipients: int, sent: int, failed: int, opened: int, clicked: int, unsubscribed: int, open_rate: float, click_rate: float}
     */
    public function stats(): array
    {
        $recipients = $this->recipients();
        $sent = (clone $recipients)->where('status', 'sent')->count();
        $opened = (clone $recipients)->whereNotNull('opened_at')->count();
        $clicked = (clone $recipients)->whereNotNull('clicked_at')->count();

        return [
            'recipients' => (clone $recipients)->count(),
            'sent' => $sent,
            'failed' => (clone $recipients)->where('status', 'failed')->count(),
            'opened' => $opened,
            'clicked' => $clicked,
            'unsubscribed' => (clone $recipients)->whereHas('contact', fn ($contact) => $contact->whereNotNull('unsubscribed_at')
                ->whereColumn('crm_contacts.unsubscribed_at', '>=', 'crm_campaign_recipients.sent_at'))->count(),
            'open_rate' => $sent ? round($opened / $sent * 100, 1) : 0.0,
            'click_rate' => $sent ? round($clicked / $sent * 100, 1) : 0.0,
        ];
    }
}
