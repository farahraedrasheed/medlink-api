<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Favorite extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['citizen_id', 'favorite_type', 'favorite_id', 'favorite_data'];

    protected $casts = [
        'favorite_data' => 'array',
        'created_at'    => 'datetime',
    ];

    public function citizen()
    {
        return $this->belongsTo(User::class, 'citizen_id');
    }
}
