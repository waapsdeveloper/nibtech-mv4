<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
