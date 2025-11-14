<?php

namespace App\Exports;

use App\Models\Transaction;
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

class TransactionsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    protected $transactions;

    public function __construct($transactions = null)
    {
        $this->transactions = $transactions;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        if ($this->transactions) {
            return collect($this->transactions);
        }

        // If no transactions provided, get all transactions
        return Transaction::orderBy('transaction_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Requested By',
            'Approved By',
            'Name of Receiver',
            'Unit/Sections',
            'Item Name',
            'Quantity',
            'Transaction Time',
            'Role',
            'Status'
        ];
    }

    /**
     * @param mixed $transaction
     * @return array
     */
    public function map($transaction): array
    {
        // Handle both array format (from frontend) and Transaction model format
        if (is_array($transaction)) {
            return [
                $transaction['requested_by'] ?? 'N/A',
                $transaction['approver_name'] ?? $transaction['approved_by'] ?? 'N/A',
                $transaction['borrower_name'] ?? 'N/A',
                $transaction['location'] ?? 'N/A',
                $transaction['item_name'] ?? 'N/A',
                $transaction['quantity'] ?? 0,
                $transaction['transaction_time'] ?? 'N/A',
                $transaction['role'] ?? 'USER',
                $transaction['status'] ?? 'Pending'
            ];
        }

        // Handle Transaction model
        $role = $transaction->role ?? 'USER';
        if (strtolower($role) === 'admin' || strtolower($role) === 'user' || strtolower($role) === 'supply') {
            $role = strtoupper($role);
        }
        
        $status = $transaction->status ?? 'Pending';
        $statusLower = strtolower($status);
        if ($statusLower === 'approved' || $statusLower === 'rejected' || $statusLower === 'pending') {
            $status = ucfirst($statusLower);
        }
        
        $approverName = $transaction->approved_by ?? 'N/A';
        $requestedBy = $transaction->requested_by ?? 'N/A';
        
        return [
            $requestedBy,
            $approverName,
            $transaction->borrower_name ?? 'N/A',
            $transaction->location ?? 'N/A',
            $transaction->item_name ?? 'N/A',
            $transaction->quantity ?? 0,
            $transaction->transaction_time ? $transaction->transaction_time->format('Y-m-d H:i:s') : 'N/A',
            $role,
            $status
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,  // Requested By
            'B' => 18,  // Approved By
            'C' => 20,  // Name of Receiver
            'D' => 15,  // Unit/Sections
            'E' => 25,  // Item Name
            'F' => 10,  // Quantity
            'G' => 25,  // Transaction Time
            'H' => 12,  // Role
            'I' => 12,  // Status
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
                        // Center the logo perfectly in merged cell
                        $columnWidths = [
                            'A' => 18, 'B' => 18, 'C' => 20, 'D' => 15, 
                            'E' => 25, 'F' => 10, 'G' => 25, 'H' => 12, 'I' => 12
                        ];
                        
                        // Calculate total width of merged range
                        $totalWidth = 0;
                        $widthBeforeE = 0;
                        
                        foreach (range('A', $highestColumn) as $col) {
                            $width = $sheet->getColumnDimension($col)->getWidth();
                            if ($width == -1 || $width == 0) {
                                $width = $columnWidths[$col] ?? 15;
                            }
                            $totalWidth += $width;
                            
                            if ($col < 'E') {
                                $widthBeforeE += $width;
                            }
                        }
                        
                        $widthE = $sheet->getColumnDimension('E')->getWidth();
                        if ($widthE == -1 || $widthE == 0) {
                            $widthE = $columnWidths['E'] ?? 25;
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
                
                // Row 6: TRANSACTIONS REPORT
                $sheet->mergeCells('A6:' . $highestColumn . '6');
                $sheet->setCellValue('A6', 'TRANSACTIONS REPORT');
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

