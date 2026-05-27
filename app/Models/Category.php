<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Category extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['name', 'description', 'icon'];

    public function medicines()
    {
        return $this->hasMany(Medicine::class);
    }

    public function getMedicineCountAttribute(): int
    {
        return $this->medicines()->where('is_active', true)->count();
    }
}
