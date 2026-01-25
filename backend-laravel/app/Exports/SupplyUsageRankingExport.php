<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class SupplyUsageRankingExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    protected $data;
    protected $year;
    protected $summary;

    public function __construct($data, $year, $summary)
    {
        $this->data = $data;
        $this->year = $year;
        $this->summary = $summary;
    }

    /**
     * @return array
     */
    public function array(): array
    {
        $rows = [];
        foreach ($this->data as $index => $supply) {
            $rows[] = [
                $index + 1,
                $supply['item']['unit'] ?? 'Item ' . $supply['item_id'],
                $supply['total_usage'],
                number_format($supply['avg_usage'], 2),
                $supply['recent_usage'],
                ucfirst($supply['trend']),
            ];
        }
        return $rows;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Rank',
            'Supply Item',
            'Total Usage',
            'Avg/Quarter',
            'Recent Usage',
            'Trend',
        ];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'A' => 10,  // Rank
            'B' => 30,  // Supply Item
            'C' => 15,  // Total Usage
            'D' => 15,  // Avg/Quarter
            'E' => 15,  // Recent Usage
            'F' => 12,  // Trend
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
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
                
                // Insert 9 rows before row 1 for header section
                $sheet->insertNewRowBefore(1, 9);
                
                // After insertion, headings are now at row 10, data starts at row 11
                $newHeadingsRow = 10;
                $newFirstDataRow = 11;
                
                // Header section - Row 1: Republic of the Philippines
                $sheet->mergeCells('A1:' . $highestColumn . '1');
                $sheet->setCellValue('A1', 'REPUBLIC OF THE PHILIPPINES');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 18, 'name' => 'Calibri', 'color' => ['rgb' => '1a1a1a']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Row 2: National Irrigation Administration
                $sheet->mergeCells('A2:' . $highestColumn . '2');
                $sheet->setCellValue('A2', 'National Irrigation Administration');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Calibri', 'color' => ['rgb' => '1a1a1a']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Row 3: Region XI
                $sheet->mergeCells('A3:' . $highestColumn . '3');
                $sheet->setCellValue('A3', 'Region XI');
                $sheet->getStyle('A3')->applyFromArray([
                    'font' => ['bold' => false, 'size' => 13, 'name' => 'Calibri', 'color' => ['rgb' => '333333']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Row 4: Logo (centered)
                $sheet->mergeCells('A4:' . $highestColumn . '4');
                $sheet->getRowDimension(4)->setRowHeight(95);
                $sheet->setCellValue('A4', ''); // Set empty value to ensure row exists
                // Center align the merged cell for the logo
                $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                
                $logoPath = \App\Support\Logo::path();
                if (file_exists($logoPath)) {
                    try {
                        // Center the logo perfectly in merged cell A4:F4
                        // Column widths for A through F
                        $columnWidths = [
                            'A' => 10, 'B' => 30, 'C' => 15, 
                            'D' => 15, 'E' => 15, 'F' => 12
                        ];
                        
                        // Calculate total width of merged range A-F
                        $totalWidth = 0;
                        $widthBeforeC = 0; // Width from A to B
                        
                        foreach (range('A', 'F') as $col) {
                            $width = $sheet->getColumnDimension($col)->getWidth();
                            if ($width == -1 || $width == 0) {
                                $width = $columnWidths[$col] ?? 15;
                            }
                            $totalWidth += $width;
                            
                            // Calculate width before column C
                            if ($col < 'C') {
                                $widthBeforeC += $width;
                            }
                        }
                        
                        // Get column C width
                        $widthC = $sheet->getColumnDimension('C')->getWidth();
                        if ($widthC == -1 || $widthC == 0) {
                            $widthC = $columnWidths['C'] ?? 15;
                        }
                        
                        // Calculate center point of the entire range
                        $centerPoint = $totalWidth / 2;
                        
                        // Calculate where column C center is
                        $columnCCenter = $widthBeforeC + ($widthC / 2);
                        
                        // Calculate the difference (offset needed)
                        // Excel uses character units, convert to pixels (approximately 7 pixels per character unit)
                        $pixelsPerChar = 7;
                        $offsetInChars = $centerPoint - $columnCCenter;
                        $offsetX = $offsetInChars * $pixelsPerChar;
                        
                        // Logo dimensions
                        $logoWidth = 90;
                        $logoHeight = 90;
                        
                        // Adjust to center the logo itself (subtract half logo width)
                        $offsetX = $offsetX - ($logoWidth / 2);
                        
                        $drawing = new Drawing();
                        $drawing->setName('NIA Logo');
                        $drawing->setDescription('NIA Logo');
                        $drawing->setPath($logoPath);
                        $drawing->setHeight($logoHeight);
                        $drawing->setWidth($logoWidth);
                        
                        // Position at C4 (center column) with calculated offset
                        $drawing->setCoordinates('C4');
                        $drawing->setOffsetX((int)round($offsetX));
                        $drawing->setOffsetY(0); // Center vertically
                        $drawing->setWorksheet($sheet);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to insert logo in Excel: ' . $e->getMessage());
                    }
                }
                
                // Row 5: Empty row for spacing
                $sheet->mergeCells('A5:' . $highestColumn . '5');
                $sheet->setCellValue('A5', '');
                $sheet->getRowDimension(5)->setRowHeight(10);
                
                // Row 6: Empty row for spacing before title
                $sheet->mergeCells('A6:' . $highestColumn . '6');
                $sheet->setCellValue('A6', '');
                $sheet->getRowDimension(6)->setRowHeight(8);
                
                // Row 7: Report Title Section Background
                $sheet->mergeCells('A7:' . $highestColumn . '7');
                $sheet->getStyle('A7')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8F9FA'],
                    ],
                ]);
                
                // Row 7: Report Title
                $sheet->setCellValue('A7', 'SUPPLY USAGE RANKING REPORT');
                $sheet->getStyle('A7')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 15, 'name' => 'Calibri', 'color' => ['rgb' => '059669']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Row 8: For the Year
                $sheet->mergeCells('A8:' . $highestColumn . '8');
                $sheet->setCellValue('A8', 'For the Year ' . $this->year);
                $sheet->getStyle('A8')->applyFromArray([
                    'font' => ['bold' => false, 'size' => 11, 'name' => 'Calibri', 'color' => ['rgb' => '555555']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Update row references after adding new row
                $newHeadingsRow = 10;
                $newFirstDataRow = 11;
                
                // Style header row with gradient effect
                $headerRange = 'A' . $newHeadingsRow . ':' . $highestColumn . $newHeadingsRow;
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11, 'name' => 'Calibri'],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '059669'], // Green background
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '047857'],
                        ],
                    ],
                ]);
                
                // Center align Rank column header
                $sheet->getStyle('A' . $newHeadingsRow)->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
                
                // Right align numeric column headers
                $sheet->getStyle('C' . $newHeadingsRow . ':E' . $newHeadingsRow)->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                
                // Style data rows
                if ($highestRow >= $newFirstDataRow) {
                    $dataRange = 'A' . $newFirstDataRow . ':' . $highestColumn . $highestRow;
                    $sheet->getStyle($dataRange)->applyFromArray([
                        'font' => ['size' => 10, 'name' => 'Calibri', 'color' => ['rgb' => '1a1a1a']],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'E5E7EB'],
                            ],
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                    
                    // Style Rank column with green color
                    $sheet->getStyle('A' . $newFirstDataRow . ':A' . $highestRow)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '059669']],
                    ]);
                    
                    // Right align numeric columns (C, D, E) with monospace font
                    $sheet->getStyle('C' . $newFirstDataRow . ':E' . $highestRow)->applyFromArray([
                        'font' => ['name' => 'Courier New'],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_RIGHT,
                        ],
                    ]);
                    
                    // Center align Rank column
                    $sheet->getStyle('A' . $newFirstDataRow . ':A' . $highestRow)->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);
                    
                    // Alternate row colors
                    for ($row = $newFirstDataRow; $row <= $highestRow; $row++) {
                        if (($row - $newFirstDataRow) % 2 == 1) {
                            $sheet->getStyle('A' . $row . ':' . $highestColumn . $row)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F3F4F6'],
                                ],
                            ]);
                        }
                    }
                }
                
                // Add spacing row before summary
                $spacingRow = $highestRow + 1;
                $sheet->mergeCells('A' . $spacingRow . ':' . $highestColumn . $spacingRow);
                $sheet->getRowDimension($spacingRow)->setRowHeight(8);
                
                // Add summary footer after data
                $summaryRow = $highestRow + 2;
                $sheet->mergeCells('A' . $summaryRow . ':' . $highestColumn . $summaryRow);
                $summaryText = 'Total Items: ' . $this->summary['total_items'] . 
                              ' | Total Usage: ' . number_format($this->summary['total_usage_all']) . 
                              ' units | Average Usage: ' . number_format($this->summary['avg_usage_all'], 2) . 
                              ' units per item';
                $sheet->setCellValue('A' . $summaryRow, $summaryText);
                $sheet->getStyle('A' . $summaryRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri', 'color' => ['rgb' => '1a1a1a']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8F9FA'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'top' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'E5E7EB'],
                        ],
                    ],
                ]);
                
                // Add "End of Report" row
                $endRow = $summaryRow + 1;
                $sheet->mergeCells('A' . $endRow . ':' . $highestColumn . $endRow);
                $sheet->setCellValue('A' . $endRow, 'End of Report');
                $sheet->getStyle('A' . $endRow)->applyFromArray([
                    'font' => ['italic' => true, 'size' => 9, 'name' => 'Calibri', 'color' => ['rgb' => '999999']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Set row heights for better spacing
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(2)->setRowHeight(24);
                $sheet->getRowDimension(3)->setRowHeight(20);
                $sheet->getRowDimension(4)->setRowHeight(95); // Logo row
                $sheet->getRowDimension(5)->setRowHeight(12); // Spacing
                $sheet->getRowDimension(7)->setRowHeight(28); // Report title section
                $sheet->getRowDimension(8)->setRowHeight(20); // Year
                $sheet->getRowDimension($newHeadingsRow)->setRowHeight(30); // Table header
                $sheet->getRowDimension($summaryRow)->setRowHeight(22);
                $sheet->getRowDimension($endRow)->setRowHeight(20);
                
                // Set default row height for data rows
                for ($row = $newFirstDataRow; $row <= $highestRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(20);
                }
            },
        ];
    }
}
