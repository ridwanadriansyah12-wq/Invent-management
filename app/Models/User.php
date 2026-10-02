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

    // ── Role checks (PRISM Stock RBAC) ──────────────────────────────────────
    public function isAdmin(): bool       { return $this->role === 'admin'; }
    public function isApprover(): bool    { return $this->role === 'approver'; }
    public function isStaff(): bool       { return $this->role === 'staff'; }

    // Backward-compat aliases
    public function isIT(): bool          { return $this->isAdmin(); }
    public function isProcurement(): bool { return $this->isApprover(); }
    public function isGudang(): bool      { return $this->isStaff(); }

    public function getRoleLabelAttribute(): string
    {
        return match($this->role) {
            'admin'       => 'Administrator',
            'approver'    => 'Approver',
            'staff'       => 'Staff Gudang',
            default       => ucfirst($this->role),
        };
    }

    // ── Scopes ──────────────────────────────────────────────────────────────
    public function scopeActive($query)  { return $query->where('is_active', true); }
    public function scopeByRole($query, string $role) { return $query->where('role', $role); }

    // ── Relations ───────────────────────────────────────────────────────────

    /** Gerakan stok yang dicatat oleh user ini */
    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @deprecated Gunakan movements() */
    public function transactions()
    {
        return $this->movements();
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** Parameter inventory yang di-review oleh user ini (role: procurement) */
    public function reviewedParameters()
    {
        return $this->hasMany(InventoryParameter::class, 'reviewed_by');
    }
}
