<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountCategory extends Model
{
    use HasFactory;

    protected $table = 'account_categories';

    protected $fillable = [
        'title',
    ];

    /**
     * Accounts associated with this category.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'account_category_id');
    }
}
