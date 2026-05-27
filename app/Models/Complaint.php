<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'reporter_id', 'against_pharmacy_id', 'subject', 'details',
        'severity', 'status', 'assigned_admin_id', 'resolution', 'resolution_date',
    ];

    protected $casts = [
        'resolution_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            if (! $c->id) {
                $c->id = 'CP-' . now()->getTimestampMs();
            }
        });
    }

    public function reporter()     { return $this->belongsTo(User::class, 'reporter_id'); }
    public function pharmacy()     { return $this->belongsTo(User::class, 'against_pharmacy_id'); }
    public function assignedAdmin(){ return $this->belongsTo(User::class, 'assigned_admin_id'); }
}
