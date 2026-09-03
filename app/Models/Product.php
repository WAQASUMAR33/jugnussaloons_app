<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'price',
        'discount',
        'discounted_price',
        'stock',
        'low_stock',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'stock' => 'integer',
        'low_stock' => 'integer',
    ];

    /**
     * Store stocks relationship.
     */
    public function storeStocks()
    {
        return $this->hasMany(ProductStoreStock::class, 'product_id');
    }

    /**
     * Stores through pivot.
     */
    public function stores()
    {
        return $this->belongsToMany(Store::class, 'product_store_stocks', 'product_id', 'store_id')
            ->withPivot(['stock', 'low_stock'])
            ->withTimestamps();
    }

    /**
     * Get stock in a specific store (or 0 if not found).
     */
    public function stockInStore(?int $storeId): int
    {
        if (!$storeId) {
            return (int) $this->stock;
        }

        if ($this->relationLoaded('storeStocks')) {
            $stockRecord = $this->storeStocks->where('store_id', $storeId)->first();
            if ($stockRecord) {
                return (int) $stockRecord->stock;
            }
        }

        return (int) (ProductStoreStock::where('product_id', $this->id)
            ->where('store_id', $storeId)
            ->value('stock') ?? 0);
    }

    /**
     * Set/update stock for a specific store and sync aggregate total stock.
     */
    public function updateStoreStock(int $storeId, int $quantity, ?int $lowStock = null): ProductStoreStock
    {
        $stockRecord = ProductStoreStock::updateOrCreate(
            ['product_id' => $this->id, 'store_id' => $storeId],
            [
                'stock' => max(0, $quantity),
                'low_stock' => $lowStock !== null ? max(0, $lowStock) : ($this->low_stock ?? 5),
            ]
        );

        if ($this->relationLoaded('storeStocks')) {
            $this->unsetRelation('storeStocks');
        }

        $this->syncTotalStock();

        return $stockRecord;
    }

    /**
     * Increment stock in a specific store and sync total stock.
     */
    public function incrementStoreStock(int $storeId, int $quantity = 1): void
    {
        $stockRecord = ProductStoreStock::firstOrCreate(
            ['product_id' => $this->id, 'store_id' => $storeId],
            ['stock' => 0, 'low_stock' => $this->low_stock ?? 5]
        );

        $stockRecord->increment('stock', $quantity);

        if ($this->relationLoaded('storeStocks')) {
            $this->unsetRelation('storeStocks');
        }

        $this->syncTotalStock();
    }

    /**
     * Decrement stock in a specific store and sync total stock.
     */
    public function decrementStoreStock(int $storeId, int $quantity = 1): void
    {
        $stockRecord = ProductStoreStock::firstOrCreate(
            ['product_id' => $this->id, 'store_id' => $storeId],
            ['stock' => 0, 'low_stock' => $this->low_stock ?? 5]
        );

        $stockRecord->decrement('stock', $quantity);

        if ($this->relationLoaded('storeStocks')) {
            $this->unsetRelation('storeStocks');
        }

        $this->syncTotalStock();
    }

    /**
     * Synchronize total aggregate stock in products table.
     */
    public function syncTotalStock(): int
    {
        $total = (int) ProductStoreStock::where('product_id', $this->id)->sum('stock');
        $this->stock = $total;
        $this->saveQuietly();
        return $total;
    }

    /**
     * Check if product current stock is at or below the low stock threshold.
     */
    public function isLowStock(?int $storeId = null): bool
    {
        if ($storeId) {
            $stockInStore = $this->stockInStore($storeId);
            $stockRecord = $this->storeStocks->where('store_id', $storeId)->first();
            $threshold = $stockRecord ? $stockRecord->low_stock : ($this->low_stock ?? 5);
            return $stockInStore <= $threshold;
        }

        return $this->stock <= ($this->low_stock ?? 5);
    }

    /**
     * Get calculated discounted price if not set manually.
     */
    public function calculateDiscountedPrice(): float
    {
        if ($this->discount > 0) {
            return round($this->price - ($this->price * ($this->discount / 100)), 2);
        }
        return (float) $this->price;
    }
}
