<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'pharmacy_id', 'medicine_id', 'quantity', 'price', 'expiry_date', 'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'price'        => 'float',
        'expiry_date'  => 'date',
    ];

    public function pharmacy()
    {
        return $this->belongsTo(User::class, 'pharmacy_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}
