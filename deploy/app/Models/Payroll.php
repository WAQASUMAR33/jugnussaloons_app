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
        'net_salary',
        'status',
        'payment_date',
        'notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'leave_deduction' => 'decimal:2',
        'total_commission' => 'decimal:2',
        'bonus' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'payment_date' => 'date',
    ];

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
}
