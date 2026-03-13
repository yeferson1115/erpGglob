<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingBroadcast extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel',
        'audience_type',
        'audience_segment',
        'user_id',
        'frequency',
        'scheduled_for',
        'is_automated',
        'message',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'is_automated' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
