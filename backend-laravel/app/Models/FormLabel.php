<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormLabel extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'placeholder',
        'section_title',
        'section_subtitle',
        'helper_text',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get label by key
     */
    public static function getByKey($key)
    {
        return self::where('key', $key)->first();
    }

    /**
     * Get all labels as key-value array
     */
    public static function getAllAsArray()
    {
        return self::all()->keyBy('key')->toArray();
    }
}
