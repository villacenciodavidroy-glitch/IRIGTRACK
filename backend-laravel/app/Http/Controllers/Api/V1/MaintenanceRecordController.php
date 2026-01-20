<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRecord;
use App\Exports\MaintenanceRecordsExport;
use Maatwebsite\Excel\Facades\Excel;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MaintenanceRecordController extends Controller
{
    /**
     * Display a listing of maintenance records with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = MaintenanceRecord::with([
                'item.category',
                'item.location',
                'item.condition',
                'conditionBefore',
                'conditionAfter'
            ])
            ->orderBy('maintenance_date', 'desc')
            ->orderBy('created_at', 'desc');

            // Apply search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('reason', 'like', "%{$search}%")
                      ->orWhere('technician_notes', 'like', "%{$search}%")
                      ->orWhereHas('item', function($itemQuery) use ($search) {
                          $itemQuery->where('unit', 'like', "%{$search}%")
                                    ->orWhere('description', 'like', "%{$search}%")
                                    ->orWhere('pac', 'like', "%{$search}%");
                      })
                      ->orWhereHas('item.location', function($locationQuery) use ($search) {
                          $locationQuery->where('name', 'like', "%{$search}%");
                      });
                });
            }

            // Apply date range filter
            if ($request->has('start_date') && !empty($request->start_date)) {
                $endDate = $request->end_date ?? now()->endOfDay();
                $query->whereBetween('maintenance_date', [$request->start_date, $endDate]);
            }

            // Apply item filter
            if ($request->has('item_id') && !empty($request->item_id)) {
                $query->where('item_id', $request->item_id);
            }

            // Apply reason filter
            if ($request->has('reason') && !empty($request->reason)) {
                $query->where('reason', 'like', "%{$request->reason}%");
            }

            // Get pagination parameters
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);

            // Execute query with pagination
            $maintenanceRecords = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform the data for frontend
            $transformedRecords = $maintenanceRecords->map(function ($record) {
                return [
                    'id' => $record->id,
                    'item_id' => $record->item_id,
                    'item_uuid' => $record->item->uuid ?? null,
                    'item_unit' => $record->item->unit ?? 'N/A',
                    'item_description' => $record->item->description ?? 'N/A',
                    'item_pac' => $record->item->pac ?? 'N/A',
                    'item_location' => $record->item->location->name ?? 'N/A',
                    'item_category' => $record->item->category->name ?? 'N/A',
                    'maintenance_date' => $record->maintenance_date ? $record->maintenance_date->format('Y-m-d') : null,
                    'maintenance_date_formatted' => $record->maintenance_date ? $record->maintenance_date->format('M d, Y') : 'N/A',
                    'reason' => $record->reason ?? 'N/A',
                    'technician_notes' => $record->technician_notes ?? '',
                    'condition_before' => $record->conditionBefore->condition ?? 'N/A',
                    'condition_after' => $record->conditionAfter->condition ?? 'N/A',
                    'created_at' => $record->created_at->toISOString(),
                    'created_at_formatted' => $record->created_at->format('M d, Y h:i A'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedRecords,
                'pagination' => [
                    'current_page' => $maintenanceRecords->currentPage(),
                    'last_page' => $maintenanceRecords->lastPage(),
                    'per_page' => $maintenanceRecords->perPage(),
                    'total' => $maintenanceRecords->total(),
                    'from' => $maintenanceRecords->firstItem(),
                    'to' => $maintenanceRecords->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch maintenance records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export maintenance records to Excel
     */
    public function export(Request $request)
    {
        try {
            $recordsParam = $request->input('records'); // Optional: JSON string of records from frontend

            $fileName = 'Maintenance_Records_' . date('Y-m-d_His') . '.xlsx';
            
            // Decode records if provided as JSON string
            $records = null;
            if ($recordsParam) {
                $decodedRecords = is_string($recordsParam) ? json_decode($recordsParam, true) : $recordsParam;
                if (is_array($decodedRecords) && count($decodedRecords) > 0) {
                    $records = $decodedRecords;
                }
            }
            
            if ($records) {
                // Export filtered records from frontend
                return Excel::download(new MaintenanceRecordsExport($records), $fileName);
            } else {
                // Export all records
                return Excel::download(new MaintenanceRecordsExport(null), $fileName);
            }
        } catch (\Exception $e) {
            \Log::error('Error exporting maintenance records: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to export maintenance records: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Export maintenance records to PDF
     */
    public function exportPdf(Request $request)
    {
        try {
            $recordsParam = $request->input('records');
            
            // Get records
            $records = [];
            if ($recordsParam) {
                $decodedRecords = is_string($recordsParam) ? json_decode($recordsParam, true) : $recordsParam;
                if (is_array($decodedRecords) && count($decodedRecords) > 0) {
                    $records = $decodedRecords;
                }
            }
            
            if (empty($records)) {
                // Get all records if none provided
                $query = MaintenanceRecord::with([
                    'item.category',
                    'item.location',
                    'conditionBefore',
                    'conditionAfter'
                ])
                ->orderBy('maintenance_date', 'desc')
                ->orderBy('created_at', 'desc');
                
                $maintenanceRecords = $query->get();
                
                $records = $maintenanceRecords->map(function ($record) {
                    // Handle null item gracefully
                    if (!$record->item) {
                        return [
                            'item_unit' => 'N/A',
                            'item_description' => 'N/A',
                            'maintenance_date' => $record->maintenance_date ? $record->maintenance_date->format('Y-m-d') : 'N/A',
                            'reason' => $record->reason ?? 'N/A',
                            'condition_before' => $record->conditionBefore ? ($record->conditionBefore->condition ?? 'N/A') : 'N/A',
                            'condition_after' => $record->conditionAfter ? ($record->conditionAfter->condition ?? 'N/A') : 'N/A',
                            'technician_notes' => $record->technician_notes ?? 'N/A',
                        ];
                    }
                    
                    return [
                        'item_unit' => $record->item->unit ?? 'N/A',
                        'item_description' => $record->item->description ?? 'N/A',
                        'maintenance_date' => $record->maintenance_date ? $record->maintenance_date->format('Y-m-d') : 'N/A',
                        'reason' => $record->reason ?? 'N/A',
                        'condition_before' => $record->conditionBefore ? ($record->conditionBefore->condition ?? 'N/A') : 'N/A',
                        'condition_after' => $record->conditionAfter ? ($record->conditionAfter->condition ?? 'N/A') : 'N/A',
                        'technician_notes' => $record->technician_notes ?? 'N/A',
                    ];
                })->toArray();
            }
            
            if (empty($records)) {
                return response()->json([
                    'message' => 'No maintenance records found to export',
                    'status' => 'error'
                ], 404);
            }
            
            // Generate HTML for PDF
            $html = $this->generatePdfHtml($records);
            
            // Check if DOMPDF classes exist (try both with and without leading backslash)
            $dompdfExists = class_exists('Dompdf\Dompdf') || class_exists('\Dompdf\Dompdf');
            $optionsExists = class_exists('Dompdf\Options') || class_exists('\Dompdf\Options');
            
            if (!$dompdfExists || !$optionsExists) {
                $errorMsg = 'DOMPDF library not installed. Please run: cd backend-laravel && composer require dompdf/dompdf && composer dump-autoload';
                \Log::error($errorMsg);
                \Log::error('Dompdf class exists: ' . ($dompdfExists ? 'Yes' : 'No'));
                \Log::error('Options class exists: ' . ($optionsExists ? 'Yes' : 'No'));
                \Log::error('Vendor path: ' . base_path('vendor'));
                \Log::error('Composer autoload: ' . (file_exists(base_path('vendor/autoload.php')) ? 'Exists' : 'Missing'));
                
                return response()->json([
                    'message' => $errorMsg,
                    'status' => 'error',
                    'error_type' => 'missing_dependency',
                    'instructions' => [
                        '1. Open terminal/command prompt',
                        '2. Navigate to: cd backend-laravel',
                        '3. Run: composer require dompdf/dompdf',
                        '4. Run: composer dump-autoload',
                        '5. Run: php artisan config:clear',
                        '6. Restart Laravel server'
                    ],
                    'debug' => config('app.debug') ? [
                        'dompdf_class' => $dompdfExists,
                        'options_class' => $optionsExists,
                        'vendor_path' => base_path('vendor'),
                        'autoload_exists' => file_exists(base_path('vendor/autoload.php')),
                        'composer_json' => file_exists(base_path('composer.json'))
                    ] : null
                ], 500);
            }
            
            // Configure DOMPDF
            try {
                $options = new Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', false); // Disable remote for security
                $options->set('defaultFont', 'Arial'); // Use Arial font
                $options->set('chroot', base_path());
                
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('A4', 'landscape');
                $dompdf->render();
            } catch (\Exception $dompdfError) {
                \Log::error('DOMPDF initialization error: ' . $dompdfError->getMessage());
                \Log::error('DOMPDF error file: ' . $dompdfError->getFile() . ' line: ' . $dompdfError->getLine());
                \Log::error('DOMPDF error trace: ' . $dompdfError->getTraceAsString());
                throw new \Exception('Failed to initialize PDF generator: ' . $dompdfError->getMessage());
            }
            
            $fileName = 'Maintenance_Records_' . date('Y-m-d_His') . '.pdf';
            
            return response()->streamDownload(function () use ($dompdf) {
                echo $dompdf->output();
            }, $fileName, [
                'Content-Type' => 'application/pdf',
            ]);
            
        } catch (\Throwable $e) {
            \Log::error('Error exporting maintenance records to PDF: ' . $e->getMessage());
            \Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            $errorMessage = 'Failed to export maintenance records to PDF';
            if (config('app.debug')) {
                $errorMessage .= ': ' . $e->getMessage();
            }
            
            return response()->json([
                'message' => $errorMessage,
                'status' => 'error',
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ] : null
            ], 500);
        }
    }

    /**
     * Generate HTML content for PDF
     */
    private function generatePdfHtml($records)
    {
        $currentDate = date('F d, Y');
        $currentYear = date('Y');
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 10px;
                    margin: 0;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .header h1 {
                    font-size: 16px;
                    font-weight: bold;
                    margin: 5px 0;
                }
                .header h2 {
                    font-size: 14px;
                    font-weight: bold;
                    margin: 5px 0;
                }
                .header p {
                    font-size: 12px;
                    margin: 5px 0;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                th {
                    background-color: #059669;
                    color: white;
                    padding: 8px;
                    text-align: left;
                    font-weight: bold;
                    border: 1px solid #000;
                }
                td {
                    padding: 6px;
                    border: 1px solid #000;
                }
                tr:nth-child(even) {
                    background-color: #f3f4f6;
                }
                .footer {
                    margin-top: 20px;
                    text-align: center;
                    font-size: 9px;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Republic of the Philippines</h1>
                <h2>National Irrigation Administration</h2>
                <p>Region XI</p>
                <h2>MAINTENANCE RECORDS REPORT</h2>
                <p>For the Year ' . $currentYear . '</p>
                <p>Generated on: ' . $currentDate . '</p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Report Date</th>
                        <th>Reason</th>
                        <th>Condition Before</th>
                        <th>Condition After</th>
                        <th>Technician Notes</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($records as $record) {
            $maintenanceDate = $record['maintenance_date'] ?? 'N/A';
            if ($maintenanceDate !== 'N/A') {
                try {
                    $date = new \DateTime($maintenanceDate);
                    $maintenanceDate = $date->format('m/d/Y');
                } catch (\Exception $e) {
                    // Keep original format if parsing fails
                }
            }
            
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($record['item_unit'] ?? 'N/A') . '<br><small>' . htmlspecialchars($record['item_description'] ?? '') . '</small></td>
                        <td>' . htmlspecialchars($maintenanceDate) . '</td>
                        <td>' . htmlspecialchars($record['reason'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($record['condition_before'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($record['condition_after'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($record['technician_notes'] ?? 'N/A') . '</td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
            <div class="footer">
                <p>Total Records: ' . count($records) . '</p>
                <p>End of Report</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
}

