<?php

namespace App\Exports;

use App\Models\Item;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Collection;

class ServiceableItemsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    protected $items;

    public function __construct($items = null)
    {
        $this->items = $items;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        if ($this->items) {
            return collect($this->items);
        }

        // If no items provided, get all items
        return Item::with(['category', 'location', 'condition', 'user'])->get();
    }

    /**
     * Get serviceable status from condition
     */
    private function getServiceableStatus($condition)
    {
        if (!$condition) {
            return 'Non-Serviceable';
        }
        
        $conditionLower = strtolower($condition);
        
        // Check for "Non - Serviceable" first (most specific)
        if (strpos($conditionLower, 'non') !== false && strpos($conditionLower, 'serviceable') !== false) {
            return 'Non-Serviceable';
        }
        // Check for "On Maintenance" or "Under Maintenance"
        else if (strpos($conditionLower, 'maintenance') !== false) {
            return 'On Maintenance';
        }
        // Check for "Serviceable"
        else if (strpos($conditionLower, 'serviceable') !== false) {
            return 'Serviceable';
        }
        
        // Default to non-serviceable for any unrecognized conditions
        return 'Non-Serviceable';
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Article',
            'Category',
            'Description',
            'Property Account Code',
            'Unit Value',
            'Date Acquired',
            'Location',
            'Condition',
            'Issued To',
            'Quantity',
            'Serviceable Status',
        ];
    }

    /**
     * @param mixed $item
     * @return array
     */
    public function map($item): array
    {
        // Handle both array and object formats
        if (is_array($item)) {
            $condition = $item['condition'] ?? '';
            $serviceableStatus = isset($item['serviceableStatus']) && $item['serviceableStatus'] 
                ? ucfirst(str_replace('-', ' ', $item['serviceableStatus'])) 
                : $this->getServiceableStatus($condition);
            
            // Format date if present
            $dateAcquired = $item['dateAcquired'] ?? $item['date_acquired'] ?? '';
            if ($dateAcquired && !empty($dateAcquired)) {
                try {
                    $date = new \DateTime($dateAcquired);
                    $dateAcquired = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    // Keep original format if parsing fails
                }
            }
            
            return [
                $item['unit'] ?? $item['article'] ?? '',
                $item['category'] ?? '',
                $item['description'] ?? '',
                $item['pac'] ?? $item['propertyAccountCode'] ?? '',
                $item['unit_value'] ?? $item['unitValue'] ?? '',
                $dateAcquired,
                $item['location'] ?? '',
                $condition,
                $item['issued_to'] ?? $item['issuedTo'] ?? 'Not Assigned',
                $item['quantity'] ?? 0,
                $serviceableStatus,
            ];
        }

        // Handle Item model - ensure relationships are loaded
        if (!$item->relationLoaded('location')) {
            $item->load('location');
        }
        if (!$item->relationLoaded('category')) {
            $item->load('category');
        }
        if (!$item->relationLoaded('condition')) {
            $item->load('condition');
        }
        if (!$item->relationLoaded('user')) {
            $item->load('user');
        }
        
        $condition = $item->condition ? ($item->condition->condition ?? '') : '';
        $serviceableStatus = $this->getServiceableStatus($condition);
        
        return [
            $item->unit ?? '',
            $item->category ? ($item->category->category ?? '') : '',
            $item->description ?? '',
            $item->pac ?? '',
            $item->unit_value ?? '',
            $item->date_acquired ? date('Y-m-d', strtotime($item->date_acquired)) : '',
            $item->location ? ($item->location->location ?? '') : '',
            $condition,
            $item->user ? (($item->user->fullname ?? '') ?: (trim(($item->user->first_name ?? '') . ' ' . ($item->user->last_name ?? '')) ?: 'Not Assigned')) : 'Not Assigned',
            $item->quantity ?? 0,
            $serviceableStatus,
        ];
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();
                
                // Insert 8 rows before row 1 for header section
                $sheet->insertNewRowBefore(1, 8);
                
                // After insertion, headings are now at row 9, data starts at row 10
                $newHeadingsRow = 9;
                $newFirstDataRow = 10;
                
                // Set column widths
                $widths = [
                    'A' => 15, 'B' => 20, 'C' => 30, 'D' => 25,
                    'E' => 15, 'F' => 18, 'G' => 20, 'H' => 20,
                    'I' => 25, 'J' => 12, 'K' => 20
                ];
                foreach ($widths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
                
                // Header section - Row 1: Republic of the Philippines
                $sheet->mergeCells('A1:K1');
                $sheet->setCellValue('A1', 'Republic of the Philippines');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Row 2: National Irrigation Administration
                $sheet->mergeCells('A2:K2');
                $sheet->setCellValue('A2', 'National Irrigation Administration');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Row 3: Region XI
                $sheet->mergeCells('A3:K3');
                $sheet->setCellValue('A3', 'Region XI');
                $sheet->getStyle('A3')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Row 4: Logo (centered)
                $sheet->mergeCells('A4:K4');
                $sheet->getRowDimension(4)->setRowHeight(80);
                $sheet->setCellValue('A4', '');
                $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                
                $logoPath = \App\Support\Logo::path();
                if (file_exists($logoPath)) {
                    try {
                        $widthBeforeF = 15 + 20 + 30 + 25 + 15; // A + B + C + D + E
                        $totalWidth = 0;
                        foreach (range('A', 'K') as $col) {
                            $width = $sheet->getColumnDimension($col)->getWidth();
                            if ($width == -1) {
                                $widths = ['A' => 15, 'B' => 20, 'C' => 30, 'D' => 25, 'E' => 15, 'F' => 18, 'G' => 20, 'H' => 20, 'I' => 25, 'J' => 12, 'K' => 20];
                                $width = $widths[$col] ?? 15;
                            }
                            $totalWidth += $width;
                        }
                        
                        $centerPoint = $totalWidth / 2;
                        $offsetX = (($centerPoint - $widthBeforeF) * 7) - 40;
                        
                        $drawing = new Drawing();
                        $drawing->setName('NIA Logo');
                        $drawing->setDescription('NIA Logo');
                        $drawing->setPath($logoPath);
                        $drawing->setHeight(80);
                        $drawing->setWidth(80);
                        $drawing->setCoordinates('F4');
                        $drawing->setOffsetX((int)round($offsetX));
                        $drawing->setOffsetY(5);
                        $drawing->setWorksheet($sheet);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to insert logo in Excel: ' . $e->getMessage());
                    }
                }
                
                // Row 5: Empty row for spacing
                $sheet->mergeCells('A5:K5');
                $sheet->setCellValue('A5', '');
                $sheet->getRowDimension(5)->setRowHeight(10);
                
                // Row 6: Title
                $sheet->mergeCells('A6:K6');
                $sheet->setCellValue('A6', 'SERVICEABLE ITEMS REPORT');
                $sheet->getStyle('A6')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Row 7: Year
                $currentYear = date('Y');
                $sheet->mergeCells('A7:K7');
                $sheet->setCellValue('A7', 'For the Year ' . $currentYear);
                $sheet->getStyle('A7')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Row 8: Empty row for spacing
                $sheet->mergeCells('A8:K8');
                $sheet->setCellValue('A8', '');
                $sheet->getRowDimension(8)->setRowHeight(10);
                
                // Style the header row (row 9 - where headings are now)
                $sheet->getStyle('A' . $newHeadingsRow . ':' . $highestColumn . $newHeadingsRow)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '059669'], // Green-600
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);
                
                // Apply borders to all data rows
                $newHighestRow = $sheet->getHighestRow();
                if ($newHighestRow > $newHeadingsRow) {
                    $sheet->getStyle('A' . ($newHeadingsRow + 1) . ':' . $highestColumn . $newHighestRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'CCCCCC'],
                            ],
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }
            },
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 20,
            'C' => 30,
            'D' => 25,
            'E' => 15,
            'F' => 18,
            'G' => 20,
            'H' => 20,
            'I' => 25,
            'J' => 12,
            'K' => 20,
        ];
    }
}
