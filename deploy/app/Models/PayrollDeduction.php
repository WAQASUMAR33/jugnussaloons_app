<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollDeduction extends Model
{
    use HasFactory;

    protected $table = 'payroll_deductions';

    protected $fillable = [
        'account_id',
        'payroll_id',
        'month_year',
        'amount',
        'reason',
        'deduction_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deduction_date' => 'date',
    ];

    /**
     * Employee account associated with this deduction.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Associated payroll run (if linked).
     */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }

    /**
     * Admin/User who recorded this deduction.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
