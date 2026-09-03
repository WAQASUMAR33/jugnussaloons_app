<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_category_id',
        'name',
        'father_name',
        'address',
        'date_of_birth',
        'date_of_anniversary',
        'phone_no1',
        'phone_no2',
        'card_no',
        'card_type',
        'emp_type',
        'username',
        'password',
        'salary',
        'balance',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_of_anniversary' => 'date',
        'salary' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    /**
     * Account category relationship.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AccountCategory::class, 'account_category_id');
    }

    /**
     * Appointments assigned to this employee.
     */
    public function appointmentsAsEmployee()
    {
        return $this->hasMany(Appointment::class, 'employee_id');
    }

    /**
     * Get average employee ranking score (1.0 - 5.0).
     */
    public function getAverageRankingAttribute(): float
    {
        $avg = $this->appointmentsAsEmployee()->whereNotNull('ranking')->avg('ranking');
        return $avg ? round((float)$avg, 1) : 0.0;
    }

    /**
     * Get total number of ranked appointments.
     */
    public function getRankingsCountAttribute(): int
    {
        return (int) $this->appointmentsAsEmployee()->whereNotNull('ranking')->count();
    }
}
