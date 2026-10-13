<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    use HasFactory;

    protected $table = 'payrolls';

    protected $fillable = [
        'account_id',
        'month_year',
        'base_salary',
        'allowed_leaves',
        'taken_leaves',
        'leave_deduction',
        'total_commission',
        'bonus',
        'deductions',
        'advance_salary',
        'net_salary',
        'status',
        'payment_date',
        'notes',
        'generated_by',
        'generated_date',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'leave_deduction' => 'decimal:2',
        'total_commission' => 'decimal:2',
        'bonus' => 'decimal:2',
        'deductions' => 'decimal:2',
        'advance_salary' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'payment_date' => 'date',
        'generated_date' => 'datetime',
    ];

    protected $appends = [
        'generated_by_name',
        'generated_date_formatted',
    ];

    /**
     * User who generated this payroll record.
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get the name of the user who generated this payroll.
     */
    public function getGeneratedByNameAttribute(): string
    {
        if ($this->relationLoaded('generator') && $this->generator) {
            return $this->generator->name;
        }
        if ($this->generated_by) {
            $user = User::find($this->generated_by);
            if ($user) return $user->name;
        }
        return 'System Admin';
    }

    /**
     * Get formatted generated date.
     */
    public function getGeneratedDateFormattedAttribute(): string
    {
        $dt = $this->generated_date ?? $this->created_at;
        return $dt ? $dt->format('d M, Y h:i A') : 'N/A';
    }

    /**
     * Employee account relationship.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Itemized payroll deductions.
     */
    public function deductionItems()
    {
        return $this->hasMany(PayrollDeduction::class, 'account_id', 'account_id')
            ->where('month_year', $this->month_year);
    }

    /**
     * Itemized salary advances.
     */
    public function advanceItems()
    {
        return $this->hasMany(SalaryAdvance::class, 'account_id', 'account_id')
            ->where('month_year', $this->month_year);
    }
}
