<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
    ];

    // ── Role checks ─────────────────────────────────────────────────────────
    public function isIT(): bool         { return $this->role === 'it'; }
    public function isProcurement(): bool { return $this->role === 'procurement'; }
    public function isGudang(): bool      { return $this->role === 'gudang'; }

    public function getRoleLabelAttribute(): string
    {
        return match($this->role) {
            'it'          => 'IT Admin',
            'procurement' => 'Procurement',
            'gudang'      => 'Gudang',
            default       => ucfirst($this->role),
        };
    }

    // ── Scopes ──────────────────────────────────────────────────────────────
    public function scopeActive($query)  { return $query->where('is_active', true); }
    public function scopeByRole($query, string $role) { return $query->where('role', $role); }

    // ── Relations ───────────────────────────────────────────────────────────
    public function transactions()
    {
        return $this->hasMany(StockTransaction::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
