<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class InventoryItem extends Model
{
    use HasUuids;

    protected $table = 'inventory';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'pharmacy_id', 'medicine_id', 'quantity', 'price', 'cost_price',
        'minimum_stock', 'maximum_stock', 'last_restock_date', 'expiry_date', 'status',
    ];

    protected $casts = [
        'last_restock_date' => 'datetime',
        'expiry_date'       => 'date',
        'price'             => 'decimal:2',
        'cost_price'        => 'decimal:2',
    ];

    // ─── Boot ────────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->status = match(true) {
                $item->quantity <= 0                        => 'out_of_stock',
                $item->quantity <= $item->minimum_stock     => 'low_stock',
                default                                     => 'in_stock',
            };
        });
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function pharmacy()
    {
        return $this->belongsTo(User::class, 'pharmacy_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}
