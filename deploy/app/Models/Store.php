<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Store extends Model
{
    use HasFactory;

    protected $table = 'stores';

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Product store stocks relationship.
     */
    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStoreStock::class, 'store_id');
    }

    /**
     * Products relationship through pivot.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_store_stocks', 'store_id', 'product_id')
            ->withPivot(['stock', 'low_stock'])
            ->withTimestamps();
    }

    /**
     * Purchases delivered to this store.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'store_id');
    }

    /**
     * Sales performed from this store.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'store_id');
    }

    /**
     * Get or create the default store singleton.
     */
    public static function getDefaultStore(): self
    {
        $default = self::where('is_default', true)->first();
        if ($default) {
            return $default;
        }

        $first = self::first();
        if ($first) {
            $first->update(['is_default' => true]);
            return $first;
        }

        return self::create([
            'name' => 'Main Branch Store',
            'code' => 'MAIN',
            'address' => 'Main Commercial District',
            'phone' => '+92 300 1234567',
            'is_active' => true,
            'is_default' => true,
        ]);
    }
}
