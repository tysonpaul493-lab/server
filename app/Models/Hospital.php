<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hospital extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'address',
        'lat',
        'lng',
        'emergency_phone',
        'emergency_services',
        'trauma_level',
        'has_trauma_center',
        'has_icu_bed',
        'total_icu_beds',
        'available_icu_beds',
        'total_general_beds',
        'available_general_beds',
        'has_cardiac_care',
        'has_stroke_unit',
        'has_blood_bank',
        'operating_hours',
        'verified',
    ];

    protected $casts = [
        'lat' => 'double',
        'lng' => 'double',
        'emergency_services' => 'array',
        'has_trauma_center' => 'boolean',
        'has_icu_bed' => 'boolean',
        'has_cardiac_care' => 'boolean',
        'has_stroke_unit' => 'boolean',
        'has_blood_bank' => 'boolean',
        'verified' => 'boolean',
    ];

    public function facilities(): HasMany
    {
        return $this->hasMany(HospitalFacility::class);
    }

    public function toNearbyArray(float $userLat, float $userLng): array
    {
        $dist = \App\Services\PhoneHelper::calculateDrivingDistanceKm($userLat, $userLng, (float) $this->lat, (float) $this->lng);
        $driveTimeMin = max(2, min(60, (int) ceil($dist * 2.4)));

        return [
            'id' => $this->slug ?: (string) $this->id,
            'db_id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
            'emergency_phone' => $this->emergency_phone ?? '',
            'emergency_services' => $this->emergency_services ?? [],
            'trauma_level' => $this->trauma_level ?? '',
            'has_trauma_center' => (bool) $this->has_trauma_center,
            'has_icu_bed' => (bool) $this->has_icu_bed,
            'available_icu_beds' => $this->available_icu_beds !== null ? (int) $this->available_icu_beds : null,
            'distance_km' => round($dist, 1),
            'estimated_drive_time' => "{$driveTimeMin} min",
        ];
    }
}
