<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Admin extends Model
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'role_name',
        'badge_number',
        'phone',
        'mfa_enabled',
        'mfa_secret',
        'api_token',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    protected $casts = [
        'mfa_enabled' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Hash::needsRehash($value) ? Hash::make($value) : $value;
    }

    public function toAuthPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'role_name' => $this->role_name,
            'badge_number' => $this->badge_number,
            'phone' => $this->phone,
            'mfa_enabled' => (bool) $this->mfa_enabled,
            'last_login_at' => $this->last_login_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
