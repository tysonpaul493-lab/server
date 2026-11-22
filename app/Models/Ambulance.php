<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ambulance extends Model
{
    protected $fillable = [
        'registration_number',
        'vehicle_type',
        'vehicle_type_display',
        'model',
        'year',
        'status',
        'assigned_driver_id',
        'hospital_affiliation',
        'equipment_list',
        'lat',
        'lng',
        'heading',
        'speed',
        'fuel_level',
        'oxygen_level',
        'last_maintenance_date',
        'last_telemetry_at',
    ];

    protected $casts = [
        'equipment_list' => 'array',
        'lat' => 'double',
        'lng' => 'double',
        'heading' => 'double',
        'speed' => 'double',
        'last_maintenance_date' => 'datetime',
        'last_telemetry_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'registration_number' => $this->registration_number,
            'plate_number' => $this->registration_number,
            'vehicle_type' => $this->vehicle_type,
            'vehicle_type_display' => $this->vehicle_type_display,
            'model' => $this->model,
            'year' => $this->year,
            'status' => $this->status,
            'hospital_affiliation' => $this->hospital_affiliation,
            'equipment_list' => $this->equipment_list ?? ['Ventilator', 'Defibrillator', 'Suction Unit', 'Oxygen Cylinders'],
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
            'heading' => (float) $this->heading,
            'speed' => (float) $this->speed,
            'fuel_level' => $this->fuel_level,
            'oxygen_level' => $this->oxygen_level,
            'driver' => $this->driver ? [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'phone' => $this->driver->phone,
            ] : null,
            'last_telemetry_at' => $this->last_telemetry_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
