<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Item;
use App\Models\User;

class SupplyRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supply_request_id',
        'item_id',
        'quantity',
        'defective_quantity',
        'status',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
    ];

    protected $casts = [
        'rejected_at' => 'datetime',
        'defective_quantity' => 'integer',
        'quantity' => 'integer',
    ];

    /** Item status: pending (default), rejected */
    public const STATUS_PENDING = 'pending';
    public const STATUS_REJECTED = 'rejected';

    public function getDefectiveQuantity(): int
    {
        return max(0, (int) ($this->defective_quantity ?? 0));
    }

    public function getApprovedQuantity(): int
    {
        if ($this->isFullyRejected()) {
            return 0;
        }

        return max(0, (int) ($this->quantity ?? 0) - $this->getDefectiveQuantity());
    }

    public function isFullyRejected(): bool
    {
        if (($this->status ?? self::STATUS_PENDING) === self::STATUS_REJECTED) {
            return true;
        }

        $requested = (int) ($this->quantity ?? 0);
        return $requested > 0 && $this->getDefectiveQuantity() >= $requested;
    }

    public function hasDefectiveUnits(): bool
    {
        return $this->getDefectiveQuantity() > 0 && !$this->isFullyRejected();
    }

    public function isRejected(): bool
    {
        return $this->isFullyRejected();
    }

    /**
     * Get the supply request this item belongs to
     */
    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class);
    }

    /**
     * Get the user who rejected this line item (if any)
     */
    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get the item for this supply request item
     * NOTE: This is NOT an Eloquent relationship - it's a helper method
     * Since item_id can be UUID or ID, we can't use a standard relationship
     * Includes soft-deleted items so historical requests can still display item information
     */
    public function item()
    {
        if (!$this->item_id) {
            return null;
        }
        
        // Check if item_id is a UUID
        $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $this->item_id);
        
        if ($isUuid) {
            return Item::withTrashed()->where('uuid', $this->item_id)->first();
        } else {
            return Item::withTrashed()->where('id', $this->item_id)->first();
        }
    }
}

