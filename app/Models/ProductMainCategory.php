<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductMainCategory extends Model
{
    use HasFactory;

    protected $table = 'product_main_categories';

    protected $fillable = [
        'title',
        'description',
        'image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
    ];

    /**
     * Sub-categories belonging to this main category.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'product_main_category_id');
    }

    /**
     * Alias for sub-categories.
     */
    public function subCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'product_main_category_id');
    }

    /**
     * Products belonging to this main category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_main_category_id');
    }

    /**
     * Purchases assigned to this main category.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'product_main_category_id');
    }

    /**
     * Sales assigned to this main category.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'product_main_category_id');
    }

    /**
     * Get accessible public URL for main category image.
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
}
