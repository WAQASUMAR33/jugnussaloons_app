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
        'image',
    ];

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
