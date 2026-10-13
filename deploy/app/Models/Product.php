<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_main_category_id',
        'product_category_id',
        'title',
        'price',
        'purchase_price',
        'discount',
        'discounted_price',
        'stock',
        'low_stock',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'stock' => 'integer',
        'low_stock' => 'integer',
    ];

    protected $appends = [
        'image_url',
        'sale_price',
        'purchase_price',
    ];

    /**
     * Purchase items relationship to determine purchase history and latest cost rate.
     */
    public function purchaseItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'product_id');
    }

    /**
     * Get purchase rate / cost price of the product (stored purchase_price, latest purchase unit price, or regular price).
     */
    public function getPurchasePriceAttribute(): float
    {
        if (isset($this->attributes['purchase_price']) && (float)$this->attributes['purchase_price'] > 0) {
            return (float)$this->attributes['purchase_price'];
        }

        if ($this->relationLoaded('purchaseItems')) {
            $latest = $this->purchaseItems->sortByDesc('id')->first();
            if ($latest && (float)$latest->unit_price > 0) {
                return (float)$latest->unit_price;
            }
        } else {
            $latest = PurchaseItem::where('product_id', $this->id)->latest('id')->first();
            if ($latest && (float)$latest->unit_price > 0) {
                return (float)$latest->unit_price;
            }
        }

        return (float) ($this->price ?? 0);
    }

    /**
     * Get computed discounted sales price (if product has a discount).
     */
    public function getSalePriceAttribute(): float
    {
        $price = (float) ($this->price ?? 0);
        $discountPct = (float) ($this->discount ?? 0);
        $discountedPrice = (float) ($this->discounted_price ?? 0);

        if ($discountedPrice > 0 && $discountedPrice < $price) {
            return $discountedPrice;
        }
        if ($discountPct > 0 && $discountPct <= 100) {
            return round($price - ($price * ($discountPct / 100)), 2);
        }
        if ($discountedPrice > 0) {
            return $discountedPrice;
        }
        return $price;
    }

    /**
     * Get accessible public URL for product image.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        return asset(ltrim($this->image, '/'));
    }

    /**
     * Main Category relationship.
     */
    public function mainCategory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductMainCategory::class, 'product_main_category_id');
    }

    /**
     * Category relationship.
     */
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

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
