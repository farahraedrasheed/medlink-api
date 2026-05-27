<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Medicine extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name', 'generic_name', 'category_id', 'strength', 'form',
        'manufacturer', 'description', 'side_effects', 'precautions',
        'active_ingredients', 'requires_prescription', 'is_controlled',
        'expiry_date', 'is_active',
    ];

    protected $casts = [
        'active_ingredients'    => 'array',
        'requires_prescription' => 'boolean',
        'is_controlled'         => 'boolean',
        'is_active'             => 'boolean',
        'expiry_date'           => 'date',
    ];

    // ─── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('generic_name', 'like', "%{$term}%")
              ->orWhere('manufacturer', 'like', "%{$term}%");
        });
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function inventory()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function pharmacies()
    {
        return $this->belongsToMany(User::class, 'inventory', 'medicine_id', 'pharmacy_id')
                    ->withPivot(['quantity', 'price', 'status', 'expiry_date'])
                    ->where('users.role', 'pharmacy')
                    ->where('users.status', 'verified');
    }

    // ─── Computed ───────────────────────────────────────────────────────────

    public function getPharmaciesCountAttribute(): int
    {
        return $this->inventory()->where('status', '!=', 'out_of_stock')->count();
    }

    public function getAveragePriceAttribute(): ?float
    {
        return $this->inventory()->avg('price');
    }

    public function getLowestPriceAttribute(): ?float
    {
        return $this->inventory()->min('price');
    }

    public function getHighestPriceAttribute(): ?float
    {
        return $this->inventory()->max('price');
    }
}
