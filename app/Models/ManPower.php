<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManPower extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    /* ────────────  Relationships  ──────────── */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class, 'id', 'project_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LogManPower::class, 'man_power_id', 'id');
    }

    /* ────────────  Accessors  (NULL ⇒ 0) ──────────── */
    public function getCurrentSalaryAttribute($value): int
    {
        return (int) ($value ?? 0);
    }

    public function getCurrentOvertimeSalaryAttribute($value): int
    {
        return (int) ($value ?? 0);
    }

    public function getTotalSalaryAttribute(): int
    {
        return $this->current_salary + $this->current_overtime_salary;
    }

    /* Opsional – versi terformat Rp 14.261.250,00 */
    public function getTotalSalaryFormattedAttribute(): string
    {
        return number_format($this->total_salary, 2, ',', '.');
    }
}
