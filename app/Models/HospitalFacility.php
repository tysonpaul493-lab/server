<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalFacility extends Model
{
    protected $fillable = [
        'hospital_id',
        'facility_name',
        'facility_code',
        'capacity_status',
        'available_units',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
