<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'transactions';
    
    protected $fillable = [
        'approved_by', 'borrower_name', 'location', 'item_name', 'quantity', 'transaction_time', 'role', 'status'
    ];

    protected $casts = [
        'transaction_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Note: approved_by now stores the full name directly, not an ID
    // Relationship removed since approved_by is now a string (name) instead of foreign key
}

