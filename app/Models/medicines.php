<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class medicines extends Model
{
    protected $fillable = [
        'name', 'active_ingredient', 'category_id', 'description',
        'manufacturer', 'requires_prescription', 'image',
    ];

    protected $casts = [
        'requires_prescription' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }
}
