<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    /** @use HasFactory<\Database\Factories\LocationFactory> */
    use HasFactory;

    protected $fillable = ['location', 'personnel', 'personnel_code'];

    /**
     * Generate a unique personnel code
     * Format: NIA-PERS-{LOCATION_CODE}-{SEQUENTIAL}
     */
    public static function generatePersonnelCode($locationName = null)
    {
        $prefix = 'NIA-PERS';
        $locationCode = 'GEN'; // Default code
        
        if ($locationName) {
            // Extract first 3-4 letters from location name
            $locationCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $locationName), 0, 4));
            if (strlen($locationCode) < 3) {
                $locationCode = 'GEN';
            }
        }
        
        // Get the highest sequential number for this location code
        // PostgreSQL compatible: use INTEGER instead of UNSIGNED
        $lastLocation = self::where('personnel_code', 'like', "{$prefix}-{$locationCode}-%")
            ->orderByRaw("CAST(SUBSTRING(personnel_code FROM LENGTH('{$prefix}-{$locationCode}-') + 1) AS INTEGER) DESC")
            ->first();
        
        $sequence = 1;
        if ($lastLocation && $lastLocation->personnel_code) {
            $parts = explode('-', $lastLocation->personnel_code);
            $lastSequence = (int) end($parts);
            $sequence = $lastSequence + 1;
        }
        
        return sprintf('%s-%s-%04d', $prefix, $locationCode, $sequence);
    }

    /**
     * Boot method to auto-generate personnel_code when personnel is set
     */
    protected static function booted()
    {
        static::updating(function ($location) {
            // Generate personnel_code if personnel is set but code is missing
            if ($location->personnel && !$location->personnel_code) {
                $location->personnel_code = self::generatePersonnelCode($location->location);
            }
        });
        
        static::creating(function ($location) {
            // Generate personnel_code if personnel is set
            if ($location->personnel && !$location->personnel_code) {
                $location->personnel_code = self::generatePersonnelCode($location->location);
            }
        });
    }

    public function item()
    {
        return $this->hasMany(Item::class);
    }

    public function users()
    {
        return $this->hasMany(\App\Models\User::class);
    }

    public function memorandumReceipts()
    {
        return $this->hasMany(MemorandumReceipt::class, 'issued_to_location_id');
    }

    public function pendingMemorandumReceipts()
    {
        return $this->hasMany(MemorandumReceipt::class, 'issued_to_location_id')
            ->where('status', 'ISSUED');
    }
}
