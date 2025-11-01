<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QRCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id', 'qr_code_data', 'image_path', 'is_active', 'version'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Scope to get only active QR codes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get QR codes by version
     */
    public function scopeByVersion($query, $version)
    {
        return $query->where('version', $version);
    }
}
