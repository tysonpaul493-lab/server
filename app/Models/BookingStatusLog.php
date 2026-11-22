<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingStatusLog extends Model
{
    protected $fillable = [
        'booking_id',
        'booking_reference',
        'from_status',
        'to_status',
        'actor_type',
        'actor_id',
        'notes',
        'lat',
        'lng',
    ];

    protected $casts = [
        'lat' => 'double',
        'lng' => 'double',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
