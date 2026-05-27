<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Review extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['citizen_id', 'pharmacy_id', 'rating', 'review_text'];

    protected $casts = ['rating' => 'decimal:1'];

    protected static function booted(): void
    {
        // Recalculate pharmacy rating after review create/update/delete
        $recalculate = function (self $review) {
            $pharmacy = User::find($review->pharmacy_id);
            if ($pharmacy) {
                $avg = Review::where('pharmacy_id', $review->pharmacy_id)->avg('rating');
                $cnt = Review::where('pharmacy_id', $review->pharmacy_id)->count();
                $pharmacy->update(['rating' => round($avg, 2), 'review_count' => $cnt]);
            }
        };
        static::saved($recalculate);
        static::deleted($recalculate);
    }

    public function citizen()  { return $this->belongsTo(User::class, 'citizen_id'); }
    public function pharmacy() { return $this->belongsTo(User::class, 'pharmacy_id'); }
}
