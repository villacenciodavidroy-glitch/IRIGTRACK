<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Item;

class SupplyRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supply_request_id',
        'item_id',
        'quantity',
    ];

    /**
     * Get the supply request this item belongs to
     */
    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class);
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

