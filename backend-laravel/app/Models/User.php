<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'fullname',
        'username',
        'email',
        'password',
        'role',
        'image',
        'location_id',
        'user_code',
        'status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function memorandumReceipts()
    {
        return $this->hasMany(MemorandumReceipt::class, 'issued_to_user_id');
    }

    public function pendingMemorandumReceipts()
    {
        return $this->hasMany(MemorandumReceipt::class, 'issued_to_user_id')
            ->where('status', 'ISSUED');
    }

    /**
     * Generate a unique user code
     * Format: NIA-{LOCATION_CODE}-{SEQUENTIAL}
     */
    public static function generateUserCode($locationId = null)
    {
        $prefix = 'NIA';
        $locationCode = 'USR'; // Default code
        
        if ($locationId) {
            $location = \App\Models\Location::find($locationId);
            if ($location && $location->location) {
                // Extract first 3-4 letters from location name
                $locationCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $location->location), 0, 4));
                if (strlen($locationCode) < 3) {
                    $locationCode = 'USR';
                }
            }
        }
        
        // Get the highest sequential number for this location code
        // PostgreSQL compatible: use INTEGER instead of UNSIGNED
        $lastUser = self::where('user_code', 'like', "{$prefix}-{$locationCode}-%")
            ->orderByRaw("CAST(SUBSTRING(user_code FROM LENGTH('{$prefix}-{$locationCode}-') + 1) AS INTEGER) DESC")
            ->first();
        
        $sequence = 1;
        if ($lastUser && $lastUser->user_code) {
            $parts = explode('-', $lastUser->user_code);
            $lastSequence = (int) end($parts);
            $sequence = $lastSequence + 1;
        }
        
        return sprintf('%s-%s-%04d', $prefix, $locationCode, $sequence);
    }

    /**
     * Boot method to auto-generate user_code on creation
     */
    protected static function booted()
    {
        static::creating(function ($user) {
            if (empty($user->user_code)) {
                $user->user_code = self::generateUserCode($user->location_id);
            }
            if (empty($user->status)) {
                $user->status = 'ACTIVE';
            }
        });
    }

    /**
     * Check if user can be resigned (no pending items)
     */
    public function canBeResigned()
    {
        return $this->pendingMemorandumReceipts()->count() === 0;
    }

    /**
     * Get count of pending items
     */
    public function getPendingItemsCountAttribute()
    {
        return $this->pendingMemorandumReceipts()->count();
    }
}
