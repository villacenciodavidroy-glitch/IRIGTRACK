<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'quantity',
        'location',
        'borrowed_by',
        'requested_by_user_id',
        'send_to',
        'status',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who approved this request
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who requested this borrow request
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * Get the item for this borrow request
     */
    public function item()
    {
        // Try to find by UUID first, then by ID
        return Item::where('uuid', $this->item_id)->orWhere('id', $this->item_id)->first();
    }

    /**
     * Get the location this request is sent to
     */
    public function sendToLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'send_to');
    }
}

