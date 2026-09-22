<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyUsage extends Model
{
    protected $fillable = ['item_id', 'year', 'month', 'total_out', 'max_daily_out'];

    protected $casts = [
        'total_out'     => 'float',
        'max_daily_out' => 'float',
    ];

    public function item() { return $this->belongsTo(Item::class); }

    public function getMonthLabelAttribute(): string
    {
        $months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        return ($months[$this->month - 1] ?? '?') . ' ' . $this->year;
    }
}
