<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'custom_title',
        'is_addon',
        'quantity',
        'purchase_price',
        'unit_price',
        'discount',
        'discount_type',
        'subtotal',
    ];

    protected $casts = [
        'is_addon' => 'boolean',
        'purchase_price' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->is_addon || empty($this->product_id)) {
            return $this->custom_title ?: 'Add-on';
        }
        return $this->product ? $this->product->title : ($this->custom_title ?: 'Product');
    }
}
