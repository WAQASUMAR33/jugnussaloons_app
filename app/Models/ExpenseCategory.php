<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $table = 'expense_categories';

    protected $fillable = [
        'title',
    ];

    /**
     * Expense relationship.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'exp_category_id', 'id');
    }
}
