<?php 

namespace App\Services\V1;

use App\Http\Resources\V1\ItemResource;
use App\Models\Item;
use App\Models\QRCode;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;
use Illuminate\Support\Facades\Validator;

class QrCodeService
{
    public function generateQrCode(Item $item)
    {

        $qrCode = $this->generate($item);

        $validator = Validator::make([
            'item_id' => $item->id,
            'qr_code_data' => $qrCode->data,
            'image_path' => $qrCode->image_path,
            'is_active' => true,
            'version' => (int)date('Y'), // Set current year as version (numeric)
        ], [
            'item_id' => 'required|exists:items,id',
            'qr_code_data' => 'required|string|max:1000',
            'image_path' => 'required|string',
            'is_active' => 'boolean',
            'version' => 'integer',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        // Save if validation passes
        QRCode::create($validator->validated());

        return $item;

    }

    protected function generate(Item $item) {
        // Load necessary relationships if not already loaded
        $item->loadMissing(['location', 'condition_number']);
        
        $itemDetails = [
            'unit' => $item->unit,
            'description' => $item->description,
            'pac' => $item->pac,
            'unit_value' => $item->unit_value,
            'location' => $item->location ? $item->location->location : '',
            'condition_number' => $item->condition_number?->condition_number,
        ];
        $qrCodeData = json_encode($itemDetails);

        $data = 'http://127.0.0.1:8000/api/v1/items/' . $item->uuid;

        // Generate QR code data with UUID or JSON-encoded details)
        $qrCodeImage = QrCodeGenerator::format('png')
            ->size(250)
            ->merge(public_path('logo.png'), 0.25, true) // optional logo
            ->generate($item->uuid);

        // Save QR code image to storage
        $directory = storage_path("app/public/qrcodes");
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $imagePath = "qrcodes/{$item->uuid}.png";
        file_put_contents(storage_path("app/public/{$imagePath}"), $qrCodeImage);

        $qrDetails = (object) [
            'data' => $data,
            'image_path' => $imagePath
        ];

        return $qrDetails;
    }

    /**
     * Validate and update QR code with incremented version
     */
    public function validateAndUpdateQrCode(Item $item, $year = null)
    {
        // Deactivate all existing QR codes for this item
        \App\Models\QRCode::where('item_id', $item->id)
            ->update(['is_active' => false]);

        // Get the highest version number for this item
        $maxVersion = \App\Models\QRCode::where('item_id', $item->id)
            ->max('version') ?? 0;

        // Increment version (1, 2, 3, etc.)
        $newVersion = (int)$maxVersion + 1;

        // Update the item's UUID to a new one
        $newUuid = (string) \Illuminate\Support\Str::uuid();
        $item->update(['uuid' => $newUuid]);

        // Generate QR code data using NEW item UUID
        $data = 'http://127.0.0.1:8000/api/v1/items/' . $item->uuid;

        // Generate QR code image
        $qrCodeImage = QrCodeGenerator::format('png')
            ->size(250)
            ->merge(public_path('logo.png'), 0.25, true)
            ->generate($item->uuid);

        // Save QR code image to storage using NEW UUID
        $directory = storage_path("app/public/qrcodes");
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        // Use NEW item UUID for image path
        $imagePath = "qrcodes/{$item->uuid}.png";
        file_put_contents(storage_path("app/public/{$imagePath}"), $qrCodeImage);

        // Create new QR code record with incremented version
        $qrCode = QRCode::create([
            'item_id' => $item->id,
            'qr_code_data' => $data,
            'image_path' => $imagePath,
            'is_active' => true,
            'version' => $newVersion,
        ]);

        return $qrCode;
    }


}