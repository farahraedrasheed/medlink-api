<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'first_name', 'last_name', 'name', 'email', 'password', 'phone',
        'address', 'profile_image', 'role', 'is_active', 'permissions',
        // pharmacy fields
        'license_number', 'license_expiry', 'latitude', 'longitude',
        'area', 'status', 'working_hours', 'delivery_available',
        'delivery_fee', 'rating', 'review_count',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
        'delivery_available'=> 'boolean',
        'working_hours'     => 'array',
        'permissions'       => 'array',
        'active_ingredients' => 'array',
        'license_expiry'    => 'date',
        'rating'            => 'decimal:2',
        'delivery_fee'      => 'decimal:2',
        'latitude'          => 'decimal:8',
        'longitude'         => 'decimal:8',
    ];

    // ─── JWT ────────────────────────────────────────────────────────────────

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'id'    => $this->id,
            'email' => $this->email,
            'role'  => $this->role,
        ];
    }

    // ─── Scopes ─────────────────────────────────────────────────────────────

    public function scopeCitizens($query)   { return $query->where('role', 'citizen'); }
    public function scopePharmacies($query) { return $query->where('role', 'pharmacy'); }
    public function scopeAdmins($query)     { return $query->where('role', 'admin'); }
    public function scopeVerified($query)   { return $query->where('status', 'verified'); }
    public function scopeActive($query)     { return $query->where('is_active', true); }

    // ─── Helpers ────────────────────────────────────────────────────────────

    public function isCitizen(): bool   { return $this->role === 'citizen'; }
    public function isPharmacy(): bool  { return $this->role === 'pharmacy'; }
    public function isAdmin(): bool     { return $this->role === 'admin'; }
    public function isVerified(): bool  { return $this->status === 'verified'; }

    public function getFullNameAttribute(): string
    {
        return $this->isPharmacy()
            ? $this->name
            : trim("{$this->first_name} {$this->last_name}");
    }

    public function isOpenNow(): bool
    {
        if (!$this->working_hours) return false;
        $day = strtolower(now()->format('l'));
        $hours = $this->working_hours[$day] ?? 'closed';
        if ($hours === 'closed') return false;

        [$open, $close] = explode('-', $hours);
        $now = now()->format('H:i');
        return $now >= $open && $now <= $close;
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function inventory()
    {
        return $this->hasMany(InventoryItem::class, 'pharmacy_id');
    }

    public function ordersAsCustomer()
    {
        return $this->hasMany(Order::class, 'citizen_id');
    }

    public function ordersAsPharmacy()
    {
        return $this->hasMany(Order::class, 'pharmacy_id');
    }

    public function broadcastRequests()
    {
        return $this->hasMany(BroadcastRequest::class, 'citizen_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'pharmacy_id');
    }

    public function myReviews()
    {
        return $this->hasMany(Review::class, 'citizen_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'citizen_id');
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class, 'reporter_id');
    }

    public function complaintsAgainst()
    {
        return $this->hasMany(Complaint::class, 'against_pharmacy_id');
    }
}
