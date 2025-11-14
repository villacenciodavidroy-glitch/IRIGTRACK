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

class LifeCyclesDataExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
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

        // If no items provided, get all items with lifespan data
        return Item::with(['category', 'location', 'condition', 'condition_number'])
            ->whereNotNull('remaining_years')
            ->get();
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
            'Years In Use',
            'Expected Lifespan (Years)',
            'Remaining (Years)',
            'Remaining (Days)',
            'End Date',
            'Status',
            'Maintenance Count'
        ];
    }

    /**
     * @param mixed $item
     * @return array
     */
    public function map($item): array
    {
        // Handle both array format (from frontend) and Item model format
        if (is_array($item)) {
            return [
                $item['unit'] ?? '',
                $item['description'] ?? '',
                $item['pac'] ?? '',
                $item['unit_value'] ?? '',
                $item['date_acquired'] ?? '',
                '', // P.O. Number - not in frontend data
                $item['location'] ?? '',
                $item['category'] ?? '',
                $item['years_in_use'] ?? 0,
                $item['expected_lifespan_years'] ?? 0,
                $item['remaining_years'] ?? 0,
                $item['remaining_days'] ?? 0,
                $item['lifespan_end_date'] ?? '',
                $item['status'] ?? 'GOOD',
                $item['maintenance_count'] ?? 0
            ];
        }

        // Handle Item model
        // Calculate years in use
        $acquisitionDate = $item->date_acquired ? new \DateTime($item->date_acquired) : null;
        $today = new \DateTime();
        $yearsInUse = 0;
        
        if ($acquisitionDate) {
            $diff = $today->diff($acquisitionDate);
            $yearsInUse = round($diff->days / 365.25, 2);
        }
        
        // Calculate remaining days
        $remainingDays = $item->remaining_years ? round($item->remaining_years * 365) : 0;
        
        // Calculate end date
        $endDate = '';
        if ($item->remaining_years) {
            $endDateTime = clone $today;
            $endDateTime->add(new \DateInterval('P' . round($item->remaining_years * 365) . 'D'));
            $endDate = $endDateTime->format('Y-m-d');
        }
        
        // Determine status
        $status = 'GOOD';
        if ($item->remaining_years <= 0.082) {
            $status = 'URGENT';
        } elseif ($item->remaining_years <= 0.164) {
            $status = 'SOON';
        } elseif ($item->remaining_years <= 0.5) {
            $status = 'MONITOR';
        }
        
        return [
            $item->unit ?? '',
            $item->description ?? '',
            $item->pac ?? '',
            $item->unit_value ?? '',
            $item->date_acquired ? date('Y-m-d', strtotime($item->date_acquired)) : '',
            $item->po_number ?? '',
            $item->location ? $item->location->location : '',
            $item->category ? $item->category->category : '',
            $yearsInUse,
            $item->lifespan_estimate ?? ($yearsInUse + ($item->remaining_years ?? 0)),
            $item->remaining_years ?? 0,
            $remainingDays,
            $endDate,
            $status,
            $item->maintenance_count ?? 0
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,  // Article
            'B' => 30,  // Description
            'C' => 25,  // Property Account Code
            'D' => 15,  // Unit Value
            'E' => 18,  // Date Acquired
            'F' => 18,  // P.O. Number
            'G' => 20,  // Location
            'H' => 20,  // Category
            'I' => 15,  // Years In Use
            'J' => 25,  // Expected Lifespan (Years)
            'K' => 18,  // Remaining (Years)
            'L' => 18,  // Remaining (Days)
            'M' => 18,  // End Date
            'N' => 15,  // Status
            'O' => 18,  // Maintenance Count
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

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
                
                // Header section - Row 1: Republic of the Philippines
                $sheet->mergeCells('A1:' . $highestColumn . '1');
                $sheet->setCellValue('A1', 'Republic of the Philippines');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Row 2: National Irrigation Administration
                $sheet->mergeCells('A2:' . $highestColumn . '2');
                $sheet->setCellValue('A2', 'National Irrigation Administration');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Row 3: Region XI
                $sheet->mergeCells('A3:' . $highestColumn . '3');
                $sheet->setCellValue('A3', 'Region XI');
                $sheet->getStyle('A3')->applyFromArray([
                    'font' => ['size' => 13],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Row 4: Logo (centered)
                $sheet->mergeCells('A4:' . $highestColumn . '4');
                $sheet->getRowDimension(4)->setRowHeight(80);
                $sheet->setCellValue('A4', ''); // Set empty value to ensure row exists
                // Center align the merged cell for the logo
                $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                
                $logoPath = public_path('logo.png');
                if (file_exists($logoPath)) {
                    try {
                        // Center the logo perfectly in merged cell A4:O4
                        // Column widths for A through O
                        $columnWidths = [
                            'A' => 15, 'B' => 30, 'C' => 25, 'D' => 15, 
                            'E' => 18, 'F' => 18, 'G' => 20, 'H' => 20, 
                            'I' => 15, 'J' => 25, 'K' => 18, 'L' => 18,
                            'M' => 18, 'N' => 15, 'O' => 18
                        ];
                        
                        // Calculate total width of merged range A-O
                        $totalWidth = 0;
                        $widthBeforeF = 0; // Width from A to E
                        
                        foreach (range('A', 'O') as $col) {
                            $width = $sheet->getColumnDimension($col)->getWidth();
                            if ($width == -1 || $width == 0) {
                                $width = $columnWidths[$col] ?? 15;
                            }
                            $totalWidth += $width;
                            
                            // Calculate width before column F
                            if ($col < 'F') {
                                $widthBeforeF += $width;
                            }
                        }
                        
                        // Get column F width
                        $widthF = $sheet->getColumnDimension('F')->getWidth();
                        if ($widthF == -1 || $widthF == 0) {
                            $widthF = $columnWidths['F'] ?? 18;
                        }
                        
                        // Calculate center point of the entire range
                        $centerPoint = $totalWidth / 2;
                        
                        // Calculate where column F center is
                        $columnFCenter = $widthBeforeF + ($widthF / 2);
                        
                        // Calculate the difference (offset needed)
                        // Excel uses character units, convert to pixels (approximately 7 pixels per character unit)
                        $pixelsPerChar = 7;
                        $offsetInChars = $centerPoint - $columnFCenter;
                        $offsetX = $offsetInChars * $pixelsPerChar;
                        
                        // Logo dimensions
                        $logoWidth = 80;
                        $logoHeight = 80;
                        
                        // Adjust to center the logo itself (subtract half logo width)
                        $offsetX = $offsetX - ($logoWidth / 2);
                        
                        $drawing = new Drawing();
                        $drawing->setName('NIA Logo');
                        $drawing->setDescription('NIA Logo');
                        $drawing->setPath($logoPath);
                        $drawing->setHeight($logoHeight);
                        $drawing->setWidth($logoWidth);
                        
                        // Position at F4 (center column) with calculated offset
                        $drawing->setCoordinates('F4');
                        $drawing->setOffsetX((int)round($offsetX));
                        $drawing->setOffsetY(0); // Center vertically
                        $drawing->setWorksheet($sheet);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to insert logo in Excel: ' . $e->getMessage());
                    }
                }
                
                // Row 5: Empty row for spacing
                $sheet->mergeCells('A5:' . $highestColumn . '5');
                $sheet->getRowDimension(5)->setRowHeight(10);
                
                // Row 6: LIFE CYCLES DATA
                $sheet->mergeCells('A6:' . $highestColumn . '6');
                $sheet->setCellValue('A6', 'LIFE CYCLES DATA');
                $sheet->getStyle('A6')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Row 7: Year
                $currentYear = date('Y');
                $sheet->mergeCells('A7:' . $highestColumn . '7');
                $sheet->setCellValue('A7', 'For the Year ' . $currentYear);
                $sheet->getStyle('A7')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Row 8: Empty row for spacing
                $sheet->mergeCells('A8:' . $highestColumn . '8');
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
                    $sheet->getStyle('A' . $newFirstDataRow . ':' . $highestColumn . $newHighestRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000'],
                            ],
                        ],
                    ]);
                    
                    // Alternating row colors
                    for ($row = $newFirstDataRow; $row <= $newHighestRow; $row++) {
                        if (($row - $newFirstDataRow) % 2 == 1) {
                            $sheet->getStyle('A' . $row . ':' . $highestColumn . $row)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F3F4F6'], // Light grey
                                ],
                            ]);
                        }
                    }
                }
            },
        ];
    }
}

