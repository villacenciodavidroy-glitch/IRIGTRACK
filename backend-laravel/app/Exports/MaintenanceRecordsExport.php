<?php

namespace App\Exports;

use App\Models\MaintenanceRecord;
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

class MaintenanceRecordsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    protected $maintenanceRecords;

    public function __construct($maintenanceRecords = null)
    {
        $this->maintenanceRecords = $maintenanceRecords;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        if ($this->maintenanceRecords) {
            return collect($this->maintenanceRecords);
        }

        // If no records provided, get all maintenance records
        return MaintenanceRecord::with([
            'item.category',
            'item.location',
            'conditionBefore',
            'conditionAfter'
        ])
        ->orderBy('maintenance_date', 'desc')
        ->orderBy('created_at', 'desc')
        ->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Item Name',
            'Description',
            'Location',
            'Category',
            'Maintenance Date',
            'Reason',
            'Condition Before',
            'Condition After',
            'Technician Notes'
        ];
    }

    /**
     * @param mixed $record
     * @return array
     */
    public function map($record): array
    {
        // Handle both array format (from frontend) and MaintenanceRecord model format
        if (is_array($record)) {
            return [
                $record['item_unit'] ?? 'N/A',
                $record['item_description'] ?? 'N/A',
                $record['item_location'] ?? 'N/A',
                $record['item_category'] ?? 'N/A',
                $record['maintenance_date'] ?? 'N/A',
                $record['reason'] ?? 'N/A',
                $record['condition_before'] ?? 'N/A',
                $record['condition_after'] ?? 'N/A',
                $record['technician_notes'] ?? 'N/A'
            ];
        }

        // Handle MaintenanceRecord model
        $item = $record->item;
        $itemName = $item ? ($item->unit ?? 'N/A') : 'N/A';
        $itemDescription = $item ? ($item->description ?? 'N/A') : 'N/A';
        $itemLocation = $item && $item->location ? $item->location->name : 'N/A';
        $itemCategory = $item && $item->category ? $item->category->name : 'N/A';
        
        $conditionBefore = $record->conditionBefore ? $record->conditionBefore->condition : 'N/A';
        $conditionAfter = $record->conditionAfter ? $record->conditionAfter->condition : 'N/A';
        
        return [
            $itemName,
            $itemDescription,
            $itemLocation,
            $itemCategory,
            $record->maintenance_date ? $record->maintenance_date->format('Y-m-d') : 'N/A',
            $record->reason ?? 'N/A',
            $conditionBefore,
            $conditionAfter,
            $record->technician_notes ?? 'N/A'
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,  // Item Name
            'B' => 30,  // Description
            'C' => 20,  // Location
            'D' => 20,  // Category
            'E' => 18,  // Maintenance Date
            'F' => 20,  // Reason
            'G' => 20,  // Condition Before
            'H' => 20,  // Condition After
            'I' => 40,  // Technician Notes
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
                $sheet->setCellValue('A4', '');
                $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                
                $logoPath = public_path('logo.png');
                if (file_exists($logoPath)) {
                    try {
                        $columnWidths = [
                            'A' => 25, 'B' => 30, 'C' => 20, 'D' => 20,
                            'E' => 18, 'F' => 20, 'G' => 20, 'H' => 20, 'I' => 40
                        ];
                        
                        $totalWidth = 0;
                        $widthBeforeE = 0;
                        
                        foreach (range('A', $highestColumn) as $col) {
                            $width = $sheet->getColumnDimension($col)->getWidth();
                            if ($width == -1 || $width == 0) {
                                $width = $columnWidths[$col] ?? 20;
                            }
                            $totalWidth += $width;
                            
                            if ($col < 'E') {
                                $widthBeforeE += $width;
                            }
                        }
                        
                        $widthE = $sheet->getColumnDimension('E')->getWidth();
                        if ($widthE == -1 || $widthE == 0) {
                            $widthE = $columnWidths['E'] ?? 18;
                        }
                        
                        $centerPoint = $totalWidth / 2;
                        $columnECenter = $widthBeforeE + ($widthE / 2);
                        $offsetInChars = $centerPoint - $columnECenter;
                        $pixelsPerChar = 7;
                        $offsetX = $offsetInChars * $pixelsPerChar;
                        
                        $logoWidth = 80;
                        $logoHeight = 80;
                        $offsetX = $offsetX - ($logoWidth / 2);
                        
                        $drawing = new Drawing();
                        $drawing->setName('NIA Logo');
                        $drawing->setDescription('NIA Logo');
                        $drawing->setPath($logoPath);
                        $drawing->setHeight($logoHeight);
                        $drawing->setWidth($logoWidth);
                        $drawing->setCoordinates('E4');
                        $drawing->setOffsetX((int)round($offsetX));
                        $drawing->setOffsetY(0);
                        $drawing->setWorksheet($sheet);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to insert logo in Excel: ' . $e->getMessage());
                    }
                }
                
                // Row 5: Empty row for spacing
                $sheet->mergeCells('A5:' . $highestColumn . '5');
                $sheet->getRowDimension(5)->setRowHeight(10);
                
                // Row 6: MAINTENANCE RECORDS REPORT
                $sheet->mergeCells('A6:' . $highestColumn . '6');
                $sheet->setCellValue('A6', 'MAINTENANCE RECORDS REPORT');
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

