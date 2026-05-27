<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    protected $fillable = [
        'citizen_id', 'pharmacy_id', 'subject', 'body', 'status', 'admin_reply',
    ];

    // status: open | in_review | resolved | rejected
    public function citizen()
    {
        return $this->belongsTo(User::class, 'citizen_id');
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
