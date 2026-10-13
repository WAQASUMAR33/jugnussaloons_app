<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_no',
        'account_id',
        'product_main_category_id',
        'store_id',
        'total_amount',
        'discount',
        'received_amount',
        'balance_due',
        'sale_date',
        'notes',
        'payment_mode',
        'bank_account_id',
        'split_payments',
        'extra_amount',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'extra_amount' => 'decimal:2',
        'split_payments' => 'array',
    ];

    /**
     * Bank account if paid via bank.
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id', 'bankid');
    }

    /**
     * Main category for this sale.
     */
    public function mainCategory(): BelongsTo
    {
        return $this->belongsTo(ProductMainCategory::class, 'product_main_category_id');
    }

    /**
     * Store location where sale occurred.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Customer account relationship.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Sale line items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }
}
