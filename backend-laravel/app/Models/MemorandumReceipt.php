<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemorandumReceipt extends Model
{

    protected $fillable = [
        'item_id',
        'issued_to_user_id',
        'issued_to_location_id',
        'issued_to_code',
        'issued_to_type',
        'issued_by_user_code',
        'issued_at',
        'returned_at',
        'status',
        'remarks',
        'reassigned_to_user_id',
        'reassigned_to_location_id',
        'reassigned_to_code',
        'reassigned_to_type',
        'processed_by_user_id'
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    /**
     * Prevent updates to issued records (read-only after creation)
     */
    protected static function booted()
    {
        static::updating(function ($mr) {
            // Only allow status updates and return operations
            $allowedFields = [
                'status', 
                'returned_at', 
                'remarks', 
                'reassigned_to_user_id', 
                'reassigned_to_location_id',
                'reassigned_to_code',
                'reassigned_to_type',
                'processed_by_user_id'
            ];
            $original = $mr->getOriginal();
            
            foreach ($mr->getDirty() as $key => $value) {
                if (!in_array($key, $allowedFields)) {
                    // Check if the field has actually changed
                    if (isset($original[$key]) && $original[$key] !== $value) {
                        throw new \Exception("Cannot modify field '{$key}' in Memorandum Receipt. MR records are read-only after creation.");
                    }
                }
            }
        });
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function issuedToUser()
    {
        return $this->belongsTo(User::class, 'issued_to_user_id');
    }

    public function issuedToLocation()
    {
        return $this->belongsTo(Location::class, 'issued_to_location_id');
    }

    public function reassignedToUser()
    {
        return $this->belongsTo(User::class, 'reassigned_to_user_id');
    }

    public function reassignedToLocation()
    {
        return $this->belongsTo(Location::class, 'reassigned_to_location_id');
    }

    public function processedByUser()
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    /**
     * Mark as returned
     */
    public function markAsReturned($processedByUserId = null, $remarks = null)
    {
        $this->status = 'RETURNED';
        $this->returned_at = now();
        if ($processedByUserId) {
            $this->processed_by_user_id = $processedByUserId;
        }
        if ($remarks) {
            $this->remarks = $remarks;
        }
        $this->save();
    }

    /**
     * Reassign to another user or personnel
     */
    public function reassignTo($newId, $newCode, $type = 'USER', $processedByUserId = null, $remarks = null)
    {
        if ($type === 'USER') {
            $this->reassigned_to_user_id = $newId;
            $this->reassigned_to_location_id = null;
        } else {
            $this->reassigned_to_location_id = $newId;
            $this->reassigned_to_user_id = null;
        }
        $this->reassigned_to_code = $newCode;
        $this->reassigned_to_type = $type;
        $this->status = 'RETURNED';
        $this->returned_at = now();
        if ($processedByUserId) {
            $this->processed_by_user_id = $processedByUserId;
        }
        if ($remarks) {
            $this->remarks = $remarks;
        }
        $this->save();
    }

    /**
     * Mark as lost or damaged
     */
    public function markAsLostOrDamaged($processedByUserId, $remarks)
    {
        $this->status = 'RETURNED';
        $this->returned_at = now();
        $this->processed_by_user_id = $processedByUserId;
        $this->remarks = $remarks;
        $this->save();
    }
}
