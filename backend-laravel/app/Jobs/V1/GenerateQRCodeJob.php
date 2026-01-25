<?php

namespace App\Jobs\V1;

use App\Models\Item;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;
use App\Models\QRCode;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;


class GenerateQRCodeJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    protected $item;

    public function __construct(Item $item)
    {
        $this->item = $item;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Generate the QR code for the item's UUID
        $itemDetails = [
            'unit' => $this->item->unit,
            'description' => $this->item->description,
            'pac' => $this->item->pac,
            'unit_value' => $this->item->unit_value,
        ];

        // Convert details to JSON string
        $qrCodeData = json_encode($itemDetails);

        // Create the QR code record in the database
        QRCode::create([
            'item_id' => $this->item->id,
            'qr_code_data' => $qrCodeData,
        ]);

        // Prepare directory for saving QR code image
        $directory = storage_path("app/public/qrcodes");
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        // Generate QR code image in PNG format (assuming using SimpleSoftwareIO\QrCode)
        $qrCodeImage = QrCodeGenerator::format('png')->size(250)->merge(\App\Support\Logo::path(), 0.3, true)->generate($qrCodeData);

        // Save image to file
        $path = $directory . "/{$this->item->uuid}.png";
        file_put_contents($path, $qrCodeImage);
    }
}
