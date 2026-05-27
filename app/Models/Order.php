<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'citizen_id', 'pharmacy_id', 'medicines', 'total_price',
        'urgency', 'notes', 'status', 'status_timeline', 'pharmacy_response',
        'response_date', 'order_date', 'expected_delivery', 'completed_date',
    ];

    protected $casts = [
        'medicines'        => 'array',
        'status_timeline'  => 'array',
        'total_price'      => 'decimal:2',
        'order_date'       => 'datetime',
        'expected_delivery'=> 'datetime',
        'completed_date'   => 'datetime',
        'response_date'    => 'datetime',
    ];

    // ─── Boot ────────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            if (! $order->id) {
                $order->id = 'ORD-' . now()->getTimestampMs();
            }
            $order->order_date = $order->order_date ?? now();
            $order->status_timeline = [[
                'status'    => 'pending',
                'timestamp' => now()->toISOString(),
                'notes'     => 'Order received',
            ]];
        });
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    public function addStatusEvent(string $status, string $notes = ''): void
    {
        $timeline   = $this->status_timeline ?? [];
        $timeline[] = [
            'status'    => $status,
            'timestamp' => now()->toISOString(),
            'notes'     => $notes,
        ];
        $this->status_timeline = $timeline;
        $this->status          = $status;

        if (in_array($status, ['delivered', 'cancelled', 'rejected'])) {
            $this->completed_date = now();
        }
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function citizen()
    {
        return $this->belongsTo(User::class, 'citizen_id');
    }

    public function pharmacy()
    {
        return $this->belongsTo(User::class, 'pharmacy_id');
    }
}
