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
        'card_expire_date',
        'emp_type',
        'image',
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
        'card_expire_date' => 'date',
        'salary' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    /**
     * Check if the customer card is expired.
     */
    public function isCardExpired(): bool
    {
        return $this->card_expire_date && $this->card_expire_date->isPast();
    }

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
     * Dedicated reviews/ratings given to this employee.
     */
    public function reviews()
    {
        return $this->hasMany(EmployeeReview::class, 'employee_id');
    }

    /**
     * Get average employee ranking score (1.0 - 5.0).
     */
    public function getAverageRankingAttribute(): float
    {
        $reviewAvg = $this->reviews()->avg('rating');
        if ($reviewAvg !== null) {
            return round((float)$reviewAvg, 1);
        }

        $aptAvg = $this->appointmentsAsEmployee()->whereNotNull('ranking')->avg('ranking');
        return $aptAvg ? round((float)$aptAvg, 1) : 0.0;
    }

    /**
     * Get total number of ranked reviews/appointments.
     */
    public function getRankingsCountAttribute(): int
    {
        $reviewCount = $this->reviews()->count();
        if ($reviewCount > 0) {
            return $reviewCount;
        }

        return (int) $this->appointmentsAsEmployee()->whereNotNull('ranking')->count();
    }

    /**
     * Salary advances received by this staff member.
     */
    public function salaryAdvances()
    {
        return $this->hasMany(SalaryAdvance::class, 'account_id');
    }
}
