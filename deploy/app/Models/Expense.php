<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $primaryKey = 'exp_id';

    protected $fillable = [
        'exp_title',
        'exp_category_id',
        'amount',
        'description',
        'added_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Category relationship.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'exp_category_id', 'id');
    }

    /**
     * User (added_by) relationship.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by', 'id');
    }
}
