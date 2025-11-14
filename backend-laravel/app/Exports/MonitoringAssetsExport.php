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

class MonitoringAssetsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    protected $items;
    protected $category;
    protected $location;

    public function __construct($items = null, $category = null, $location = null)
    {
        $this->items = $items;
        $this->category = $category;
        $this->location = $location;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        if ($this->items) {
            return collect($this->items);
        }

        $query = Item::with(['category', 'location', 'condition', 'user']);

        // Apply category filter if provided
        if ($this->category) {
            $query->whereHas('category', function ($q) {
                $q->where('category', $this->category);
            });
        }

        // Apply location filter if provided
        if ($this->location && $this->location !== 'all') {
            $query->whereHas('location', function ($q) {
                $q->where('location', $this->location);
            });
        }

        return $query->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Article',
            'Description',
            'Property Account Code',
            'Unit Value',
            'Date Acquired',
            'P.O. Number',
            'Location',
            'Category',
            'Condition',
            'Issued To',
            'Quantity',
        ];
    }

    /**
     * @param Item $item
     * @return array
     */
    public function map($item): array
    {
        // Handle both array and object formats
        if (is_array($item)) {
            // For array format, use the values as provided (should already be correct from API)
            return [
                $item['article'] ?? $item['unit'] ?? '',
                $item['description'] ?? '',
                $item['propertyAccountCode'] ?? $item['pac'] ?? '',
                $item['unitValue'] ?? $item['unit_value'] ?? '',
                $item['dateAcquired'] ?? $item['date_acquired'] ?? '',
                $item['poNumber'] ?? $item['po_number'] ?? '',
                $item['location'] ?? '',
                $item['category'] ?? '',
                $item['condition'] ?? '',
                $item['issuedTo'] ?? $item['issued_to'] ?? 'Not Assigned',
                $item['quantity'] ?? 0,
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
        
        // Get location name from relationship
        $locationName = '';
        if ($item->location) {
            $locationName = $item->location->location ?? '';
        }
        
        // Get issued_to: priority is location personnel, fallback to user fullname
        // This matches the logic in ItemResource
        $issuedTo = 'Not Assigned';
        if ($item->location && $item->location->personnel && trim($item->location->personnel) !== '') {
            // First priority: location's personnel
            $issuedTo = $item->location->personnel;
        } elseif ($item->user) {
            // Second priority: user's fullname
            if (!empty($item->user->fullname)) {
                $issuedTo = $item->user->fullname;
            } else {
                // Construct from first_name and last_name
                $fullname = trim(($item->user->first_name ?? '') . ' ' . ($item->user->last_name ?? ''));
                $issuedTo = $fullname ?: 'Not Assigned';
            }
        }
        
        return [
            $item->unit ?? '',
            $item->description ?? '',
            $item->pac ?? '',
            $item->unit_value ?? '',
            $item->date_acquired ? date('Y-m-d', strtotime($item->date_acquired)) : '',
            $item->po_number ?? '',
            $locationName,
            $item->category ? ($item->category->category ?? '') : '',
            $item->condition ? ($item->condition->condition ?? '') : '',
            $issuedTo,
            $item->quantity ?? 0,
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
                // This will push existing content (headings at row 1, data from row 2) down to rows 9+
                $sheet->insertNewRowBefore(1, 8);
                
                // After insertion, headings are now at row 9, data starts at row 10
                $newHeadingsRow = 9;
                $newFirstDataRow = 10;
                
                // Set column widths
                $widths = [
                    'A' => 15, 'B' => 30, 'C' => 25, 'D' => 15,
                    'E' => 18, 'F' => 18, 'G' => 20, 'H' => 20,
                    'I' => 15, 'J' => 25, 'K' => 12
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
                
                // Row 4: Logo (centered) - Set row height first
                $sheet->mergeCells('A4:K4');
                $sheet->getRowDimension(4)->setRowHeight(80);
                $sheet->setCellValue('A4', ''); // Set empty value to ensure row exists
                // Center align the merged cell for the logo
                $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                
                $logoPath = public_path('logo.png');
                if (file_exists($logoPath)) {
                    try {
                        // Center the logo perfectly in merged cell A4:K4
                        // Logo dimensions
                        $logoWidth = 80;
                        $logoHeight = 80;
                        
                        // Column widths for A through K (in character units)
                        // These should match the widths set earlier in the code
                        $columnWidths = [
                            'A' => 15, 'B' => 30, 'C' => 25, 'D' => 15, 
                            'E' => 18, 'F' => 18, 'G' => 20, 'H' => 20, 
                            'I' => 15, 'J' => 25, 'K' => 12
                        ];
                        
                        // Calculate total width of merged range A-K in character units
                        $totalWidth = 0;
                        foreach (range('A', 'K') as $col) {
                            $width = $sheet->getColumnDimension($col)->getWidth();
                            if ($width == -1 || $width == 0) {
                                $width = $columnWidths[$col] ?? 15;
                            }
                            $totalWidth += $width;
                        }
                        
                        // Calculate cumulative width up to column F (center column)
                        $widthBeforeF = 0;
                        foreach (range('A', 'E') as $col) {
                            $width = $sheet->getColumnDimension($col)->getWidth();
                            if ($width == -1 || $width == 0) {
                                $width = $columnWidths[$col] ?? 15;
                            }
                            $widthBeforeF += $width;
                        }
                        
                        // Get column F width
                        $widthF = $sheet->getColumnDimension('F')->getWidth();
                        if ($widthF == -1 || $widthF == 0) {
                            $widthF = $columnWidths['F'] ?? 18;
                        }
                        
                        // Calculate the exact center of the merged range
                        $rangeCenter = $totalWidth / 2;
                        
                        // Calculate where column F's left edge starts
                        $columnFLeft = $widthBeforeF;
                        
                        // Calculate where column F's center is
                        $columnFCenter = $columnFLeft + ($widthF / 2);
                        
                        // Calculate how far we need to move from F's center to the range center
                        // Excel character units: 1 char unit ≈ 7 pixels at default font size
                        $pixelsPerChar = 7;
                        $offsetInChars = $rangeCenter - $columnFCenter;
                        $offsetX = $offsetInChars * $pixelsPerChar;
                        
                        // The logo's anchor point is at its top-left corner
                        // To center it, we need to move it left by half its width
                        $offsetX = $offsetX - ($logoWidth / 2);
                        
                        $drawing = new Drawing();
                        $drawing->setName('NIA Logo');
                        $drawing->setDescription('NIA Logo');
                        $drawing->setPath($logoPath);
                        $drawing->setHeight($logoHeight);
                        $drawing->setWidth($logoWidth);
                        
                        // Position at F4 (which is roughly the center column)
                        // The offsetX will adjust it to be perfectly centered
                        $drawing->setCoordinates('F4');
                        $drawing->setOffsetX((int)round($offsetX));
                        $drawing->setOffsetY(0); // Center vertically
                        $drawing->setWorksheet($sheet);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to insert logo in Excel: ' . $e->getMessage());
                    }
                }
                
                // Row 5: Empty row for spacing
                $sheet->mergeCells('A5:K5');
                $sheet->setCellValue('A5', ''); // Ensure row exists
                $sheet->getRowDimension(5)->setRowHeight(10);
                
                // Row 6: Category name (or DESKTOP MONITORING)
                $categoryName = $this->category ? strtoupper($this->category) . ' MONITORING' : 'DESKTOP MONITORING';
                $sheet->mergeCells('A6:K6');
                $sheet->setCellValue('A6', $categoryName);
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
                $sheet->setCellValue('A8', ''); // Ensure row exists
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
        // Styles are handled in AfterSheet event
        return [];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 30,
            'C' => 25,
            'D' => 15,
            'E' => 18,
            'F' => 18,
            'G' => 20,
            'H' => 20,
            'I' => 15,
            'J' => 25,
            'K' => 12,
        ];
    }
}
