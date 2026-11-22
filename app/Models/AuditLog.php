<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'admin_name',
        'action',
        'entity_type',
        'entity_id',
        'details',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->admin_id,
            'user_name' => $this->admin_name,
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'target' => $this->entity_type . ($this->entity_id ? ' #' . $this->entity_id : ''),
            'entity_id' => $this->entity_id,
            'details' => is_array($this->details) ? json_encode($this->details) : (string) $this->details,
            'ip_address' => $this->ip_address,
            'timestamp' => $this->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
