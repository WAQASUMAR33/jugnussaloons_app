<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_logo',
        'brand_name',
        'brand_slogan',
        'brand_address',
        'brand_phone1',
        'brand_phone2',
    ];

    /**
     * Retrieve global active brand settings singleton or create default.
     */
    public static function getSettings(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'brand_name' => 'Jugnu Saloon',
                'brand_slogan' => 'Executive Hair Styling & Grooming Spa',
                'brand_address' => 'Main Boulevard, Saloon Commercial District, City Center',
                'brand_phone1' => '+92 300 1234567',
                'brand_phone2' => '+92 321 7654321',
            ]
        );
    }
}
