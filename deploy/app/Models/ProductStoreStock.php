<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStoreStock extends Model
{
    use HasFactory;

    protected $table = 'product_store_stocks';

    protected $fillable = [
        'product_id',
        'store_id',
        'stock',
        'low_stock',
    ];

    protected $casts = [
        'stock' => 'integer',
        'low_stock' => 'integer',
    ];

    /**
     * Parent product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Store location.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Check if stock is low for this specific store.
     */
    public function isLowStock(): bool
    {
        return $this->stock <= ($this->low_stock ?? 5);
    }
}
