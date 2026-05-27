<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastRequest extends Model
{
    protected $table = 'broadcast_requests';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'citizen_id', 'medicine_name', 'quantity', 'notes',
        'urgency', 'status', 'responses', 'accepted_pharmacy_id',
        'expires_at', 'closed_at',
    ];

    protected $casts = [
        'responses'  => 'array',
        'expires_at' => 'datetime',
        'closed_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $req) {
            if (! $req->id) {
                $req->id = 'REQ-' . now()->getTimestampMs();
            }
            $req->expires_at = $req->expires_at ?? now()->addHours(2);
            $req->responses  = $req->responses ?? [];
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast() && $this->status === 'open';
    }

    public function addResponse(array $response): void
    {
        $responses   = $this->responses ?? [];
        $responses[] = $response;
        $this->responses = $responses;
    }

    public function citizen()
    {
        return $this->belongsTo(User::class, 'citizen_id');
    }

    public function acceptedPharmacy()
    {
        return $this->belongsTo(User::class, 'accepted_pharmacy_id');
    }
}
