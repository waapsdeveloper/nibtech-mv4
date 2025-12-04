<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use App\Models\Admin_model;
use App\Models\Marketplace_model;
use App\Models\Order_model;
use App\Models\SupportMessage;
use App\Models\SupportTag;

class SupportThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_id',
        'marketplace_source',
        'external_thread_id',
        'order_id',
        'order_reference',
        'buyer_name',
        'buyer_email',
        'status',
        'priority',
        'change_of_mind',
        'last_external_activity_at',
        'last_synced_at',
        'assigned_to',
        'metadata',
    ];

    protected $casts = [
        'change_of_mind' => 'boolean',
        'metadata' => 'array',
        'last_external_activity_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderByDesc('sent_at');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(SupportTag::class, 'support_tag_thread');
    }
    /** @property-read SupportMessage $messages */

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order_model::class, 'order_id');
    }

    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace_model::class, 'marketplace_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin_model::class, 'assigned_to');
    }

    public function getReplyEmailAttribute(): ?string
    {
        $messages = $this->relationLoaded('messages')
            ? $this->messages
            : $this->messages()->latest('sent_at')->take(5)->get();

        if ($messages instanceof Collection) {
            $inbound = $messages
                ->filter(fn ($message) => $message->direction === 'inbound' && filter_var($message->author_email, FILTER_VALIDATE_EMAIL))
                ->last();

            if ($inbound) {
                return $inbound->author_email;
            }
        }

        return filter_var($this->buyer_email, FILTER_VALIDATE_EMAIL) ? $this->buyer_email : null;
    }

    public function getPortalUrlAttribute(): ?string
    {
        $metadata = $this->metadata ?? [];
        $rawCandidates = [
            data_get($metadata, 'portal_url'),
            data_get($metadata, 'link'),
            data_get($metadata, 'url'),
            data_get($metadata, 'ticket_url'),
            data_get($metadata, 'zendesk_link'),
        ];

        foreach ($rawCandidates as $candidate) {
            $normalized = $this->normalizePortalUrl($candidate);
            if ($normalized) {
                return $normalized;
            }
        }

        if ($this->marketplace_source === 'refurbed' && $this->external_thread_id) {
            $ticketId = ltrim((string) $this->external_thread_id, '#');
            if ($ticketId !== '') {
                return sprintf('https://refurbed-merchant.zendesk.com/agent/tickets/%s', $ticketId);
            }
        }

        return null;
    }

    protected function normalizePortalUrl($value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $url = trim($value);

        if (Str::startsWith($url, '//')) {
            $url = 'https:' . $url;
        } elseif (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://' . ltrim($url, '/');
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
