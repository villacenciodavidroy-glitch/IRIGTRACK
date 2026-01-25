<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class Logo
{
    public const STORAGE_PATH = 'logo.png';

    /**
     * Path to the logo file on disk.
     * Prefers storage (admin-uploaded) over public default.
     */
    public static function path(): string
    {
        $storagePath = storage_path('app/public/' . self::STORAGE_PATH);
        if (file_exists($storagePath)) {
            return $storagePath;
        }
        return public_path('logo.png');
    }

    /**
     * Public URL for the logo (for frontend / API).
     */
    public static function url(): string
    {
        if (self::hasCustom()) {
            return asset('storage/' . self::STORAGE_PATH);
        }
        return asset('logo.png');
    }

    /**
     * Whether an admin-uploaded logo exists in storage.
     */
    public static function hasCustom(): bool
    {
        return Storage::disk('public')->exists(self::STORAGE_PATH);
    }
}
