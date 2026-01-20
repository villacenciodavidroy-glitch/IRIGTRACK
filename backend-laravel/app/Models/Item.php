<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Item extends Model
{
    /** @use HasFactory<\Database\Factories\ItemFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'unit', 'description', 'serial_number', 'model', 'pac', 'unit_value', 'date_acquired', 'po_number', 'category_id', 'location_id',
        'condition_id', 'condition_number_id', 'user_id', 'image_path', 'quantity', 'deletion_reason',
        'maintenance_count', 'lifespan_estimate', 'remaining_years', 'maintenance_reason'
        // Note: maintenance_reason is stored in items table as free text, maintenance_records.reason is enum
    ];

    protected $casts = [
        'maintenance_count' => 'integer',
        'lifespan_estimate' => 'float',
        'remaining_years' => 'float',
        'unit_value' => 'decimal:2',
        'quantity' => 'integer',
    ];

    /**
     * Generate a unique serial number
     * Format: NIA-EQ-{YEAR}-{SEQUENTIAL}
     * Example: NIA-EQ-2024-0001
     */
    public static function generateSerialNumber()
    {
        $prefix = 'NIA-EQ';
        $currentYear = date('Y');
        
        // Get the highest sequential number for this year
        $lastItem = self::where('serial_number', 'like', "{$prefix}-{$currentYear}-%")
            ->orderByRaw("CAST(SUBSTRING(serial_number FROM LENGTH('{$prefix}-{$currentYear}-') + 1) AS INTEGER) DESC")
            ->first();
        
        $sequence = 1;
        if ($lastItem && $lastItem->serial_number) {
            $parts = explode('-', $lastItem->serial_number);
            $lastSequence = (int) end($parts);
            $sequence = $lastSequence + 1;
        }
        
        return sprintf('%s-%s-%04d', $prefix, $currentYear, $sequence);
    }

     protected static function booted()
    {
        static::creating(function ($item) {
            $item->uuid = (string) Str::uuid();
            
            // Auto-generate serial number if not provided
            if (empty($item->serial_number)) {
                $item->serial_number = self::generateSerialNumber();
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function qrCode()
    {
        return $this->hasOne(QRCode::class)->where('is_active', true)->latest();
    }

    public function qrCodes()
    {
        return $this->hasMany(QRCode::class);
    }

    public function activeQrCode()
    {
        return $this->hasOne(QRCode::class)->where('is_active', true)->latest();
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }

    public function condition()
    {
        return $this->belongsTo(Condition::class, 'condition_id', 'id');
    }

    public function condition_number()
    {
        return $this->belongsTo(ConditionNumber::class, 'condition_number_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }
    

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'item_id', 'id');
    }

    public function usages()
    {
        return $this->hasMany(ItemUsage::class, 'item_id', 'id');
    }

    public function maintenance_records()
    {
        return $this->hasMany(MaintenanceRecord::class, 'item_id', 'id');
    }
    
    public function deletedItemRecord()
    {
        return $this->hasOne(DeletedItem::class, 'item_id', 'id');
    }

    /**
     * Get the latest memorandum receipt for this item
     * This shows the current status (ISSUED, RETURNED, LOST, DAMAGED)
     * Priority: ISSUED > LOST > DAMAGED > RETURNED (to show current active status)
     */
    public function latestMemorandumReceipt()
    {
        return $this->hasOne(MemorandumReceipt::class, 'item_id', 'id')
            ->orderByRaw("CASE 
                WHEN status = 'ISSUED' THEN 1 
                WHEN status = 'LOST' THEN 2 
                WHEN status = 'DAMAGED' THEN 3 
                WHEN status = 'RETURNED' THEN 4 
                ELSE 5 
            END")
            ->orderBy('issued_at', 'desc')
            ->latest();
    }

    /**
     * Get all memorandum receipts for this item
     */
    public function memorandumReceipts()
    {
        return $this->hasMany(MemorandumReceipt::class, 'item_id', 'id');
    }
}
