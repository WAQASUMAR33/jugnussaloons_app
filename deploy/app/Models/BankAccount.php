<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;

    /**
     * Primary Key column name
     */
    protected $primaryKey = 'bankid';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'bank_name',
        'account_title',
        'account_no',
        'branch_name',
        'iban',
        'is_active',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];
}
