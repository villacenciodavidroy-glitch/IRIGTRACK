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
        'uuid', 'unit', 'description', 'pac', 'unit_value', 'date_acquired', 'po_number', 'category_id', 'location_id',
        'condition_id', 'condition_number_id', 'user_id', 'image_path', 'quantity', 'deletion_reason', 'maintenance_reason',
        'maintenance_count', 'lifespan_estimate', 'remaining_years'
    ];

    protected $casts = [
        'maintenance_count' => 'integer',
        'lifespan_estimate' => 'float',
        'remaining_years' => 'float',
        'unit_value' => 'decimal:2',
        'quantity' => 'integer',
    ];

     protected static function booted()
    {
        static::creating(function ($item) {
            $item->uuid = (string) Str::uuid();
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
}
