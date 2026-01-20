<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'quantity',
        'urgency_level',
        'notes',
        'status',
        'requested_by_user_id',
        'target_supply_account_id',
        'approved_by',
        'forwarded_to_admin_id',
        'assigned_to_admin_id',
        'admin_comments',
        'approval_proof',
        'approved_at',
        'assigned_at',
        'admin_accepted_at',
        'fulfilled_at',
        'request_number',
        'rejection_reason',
        'fulfillment_notes',
        'delivery_location',
        'expected_delivery_date',
        'cancellation_reason',
        'fulfilled_by',
        'rejected_at',
        'cancelled_at',
        'pickup_scheduled_at',
        'pickup_notified_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'assigned_at' => 'datetime',
        'admin_accepted_at' => 'datetime',
        'expected_delivery_date' => 'date',
        'pickup_scheduled_at' => 'datetime',
        'pickup_notified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who requested this supply request
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * Get the user who approved/rejected this request
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the admin this request was forwarded to
     */
    public function forwardedToAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forwarded_to_admin_id');
    }

    /**
     * Get the admin this request is assigned to
     */
    public function assignedToAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_admin_id');
    }

    /**
     * Get the target supply account this request is submitted to
     */
    public function targetSupplyAccount(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_supply_account_id');
    }

    /**
     * Get the user who fulfilled this request
     */
    public function fulfilledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    /**
     * Boot method to generate request number automatically
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($supplyRequest) {
            if (empty($supplyRequest->request_number)) {
                // Generate unique request number
                do {
                    $requestNumber = 'SR-' . strtoupper(uniqid());
                } while (self::where('request_number', $requestNumber)->exists());
                
                $supplyRequest->request_number = $requestNumber;
            }
        });
    }

    /**
     * Get the item for this supply request
     * NOTE: This is NOT an Eloquent relationship - it's a helper method
     * Do NOT use this in ->with() or ->load() - call it directly: $request->item()
     * Since item_id can be UUID or ID, we can't use a standard relationship
     * Includes soft-deleted items so historical requests can still display item information
     */
    public function item()
    {
        if (!$this->item_id) {
            return null;
        }
        
        // Check if item_id is a UUID (format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)
        $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $this->item_id);
        
        if ($isUuid) {
            // If it's a UUID, only search by UUID (include soft-deleted items)
            return Item::withTrashed()->where('uuid', $this->item_id)->first();
        } else {
            // If it's numeric, search by ID (include soft-deleted items)
            return Item::withTrashed()->where('id', $this->item_id)->first();
        }
    }

    /**
     * Get all items for this supply request
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplyRequestItem::class);
    }

    /**
     * Get all messages for this supply request
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupplyRequestMessage::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get unread messages count for this supply request
     */
    public function unreadMessagesCount()
    {
        return $this->messages()->where('is_read', false)->count();
    }
}
