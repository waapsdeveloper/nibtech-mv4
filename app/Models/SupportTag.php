<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SupportTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
    ];

    public function threads(): BelongsToMany
    {
        return $this->belongsToMany(SupportThread::class, 'support_tag_thread');
    }
}
