<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class MemorandumReceipt extends Model
{
    private static ?array $mrColumnsCache = null;

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
        $columns = $this->getMrColumns();

        if ($type === 'USER') {
            if (in_array('reassigned_to_user_id', $columns, true)) {
                $this->reassigned_to_user_id = $newId;
            }
            if (in_array('reassigned_to_location_id', $columns, true)) {
                $this->reassigned_to_location_id = null;
            }
        } else {
            if (in_array('reassigned_to_location_id', $columns, true)) {
                $this->reassigned_to_location_id = $newId;
            }
            if (in_array('reassigned_to_user_id', $columns, true)) {
                $this->reassigned_to_user_id = null;
            }
        }
        if (in_array('reassigned_to_code', $columns, true)) {
            $this->reassigned_to_code = $newCode;
        } elseif (in_array('reassigned_to_user_code', $columns, true)) {
            // Backward compatibility for older schemas that still use this column name.
            $this->reassigned_to_user_code = $newCode;
        }
        if (in_array('reassigned_to_type', $columns, true)) {
            $this->reassigned_to_type = $type;
        }
        $this->status = 'RETURNED';
        $this->returned_at = now();
        if ($processedByUserId && in_array('processed_by_user_id', $columns, true)) {
            $this->processed_by_user_id = $processedByUserId;
        }
        if ($remarks) {
            $this->remarks = $remarks;
        }
        $this->save();
    }

    private function getMrColumns(): array
    {
        if (self::$mrColumnsCache === null) {
            self::$mrColumnsCache = Schema::getColumnListing($this->getTable());
        }

        return self::$mrColumnsCache;
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
