<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Exports\TransactionsExport;
use Maatwebsite\Excel\Facades\Excel;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Get all transactions from transactions table
     */
    public function index(Request $request)
    {
        try {
            $query = Transaction::orderBy('transaction_time', 'desc')
                ->orderBy('id', 'desc');

            // Apply search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('requested_by', 'like', "%{$search}%")
                      ->orWhere('approved_by', 'like', "%{$search}%")
                      ->orWhere('borrower_name', 'like', "%{$search}%")
                      ->orWhere('item_name', 'like', "%{$search}%")
                      ->orWhere('location', 'like', "%{$search}%");
                });
            }

            // Get pagination parameters
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);

            // Execute query with pagination
            $transactions = $query->paginate($perPage, ['*'], 'page', $page);

            // Format transactions with approver name
            // Return stored values directly since they're already formatted to match frontend display
            $formattedTransactions = $transactions->map(function ($transaction) {
                // Use stored role directly (already formatted as "ADMIN", "USER", or "SUPPLY")
                // If not formatted, normalize it
                $role = $transaction->role ?? 'USER';
                if (strtolower($role) === 'admin' || strtolower($role) === 'user' || strtolower($role) === 'supply') {
                    // Convert old format to new format
                    $role = strtoupper($role);
                }
                
                // Use stored status directly (already formatted as "Approved" or "Rejected")
                // If not formatted, normalize it
                $status = $transaction->status ?? 'Pending';
                $statusLower = strtolower($status);
                if ($statusLower === 'approved' || $statusLower === 'rejected' || $statusLower === 'pending') {
                    // Convert old format to new format
                    $status = ucfirst($statusLower);
                }
                
                // approved_by now stores the full name directly, use it as-is
                $approverName = $transaction->approved_by ?? 'N/A';
                
                // Get requested_by - ensure it's not null or empty
                $requestedBy = $transaction->requested_by;
                if (empty($requestedBy) || $requestedBy === null) {
                    $requestedBy = 'N/A';
                    // Log if requested_by is missing for debugging
                    \Log::warning("Transaction ID {$transaction->id} has NULL or empty requested_by field");
                }
                
                return [
                    'id' => $transaction->id,
                    'approved_by' => $approverName, // This is now the full name, not an ID
                    'approver_name' => $approverName, // Same as approved_by (full name)
                    'borrower_name' => $transaction->borrower_name,
                    'requested_by' => $requestedBy, // The user who sent the request
                    'location' => $transaction->location,
                    'item_name' => $transaction->item_name,
                    'quantity' => $transaction->quantity,
                    'transaction_time' => $transaction->transaction_time ? $transaction->transaction_time->format('Y-m-d H:i:s') : null,
                    'role' => $role, // Return as stored: "ADMIN", "USER", or "SUPPLY"
                    'status' => $status, // Return as stored: "Approved" or "Rejected"
                    'created_at' => $transaction->created_at ? $transaction->created_at->format('Y-m-d H:i:s') : null,
                    'updated_at' => $transaction->updated_at ? $transaction->updated_at->format('Y-m-d H:i:s') : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedTransactions,
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'from' => $transactions->firstItem(),
                    'to' => $transactions->lastItem(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching transactions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export transactions to Excel
     */
    public function exportTransactions(Request $request)
    {
        try {
            $transactionsParam = $request->input('transactions'); // Optional: JSON string of transactions from frontend

            $fileName = 'Transactions_' . date('Y-m-d_His') . '.xlsx';
            
            // Decode transactions if provided as JSON string
            $transactions = null;
            if ($transactionsParam) {
                $decodedTransactions = is_string($transactionsParam) ? json_decode($transactionsParam, true) : $transactionsParam;
                if (is_array($decodedTransactions) && count($decodedTransactions) > 0) {
                    $transactions = $decodedTransactions;
                }
            }
            
            if ($transactions) {
                // Export filtered transactions from frontend
                return Excel::download(new TransactionsExport($transactions), $fileName);
            } else {
                // Export all transactions
                return Excel::download(new TransactionsExport(null), $fileName);
            }
        } catch (\Exception $e) {
            \Log::error('Error exporting transactions: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to export transactions: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Export transactions to PDF
     */
    public function exportPdf(Request $request)
    {
        try {
            $transactionsParam = $request->input('transactions');
            
            // Get transactions
            $transactions = [];
            if ($transactionsParam) {
                $decodedTransactions = is_string($transactionsParam) ? json_decode($transactionsParam, true) : $transactionsParam;
                if (is_array($decodedTransactions) && count($decodedTransactions) > 0) {
                    $transactions = $decodedTransactions;
                }
            }
            
            if (empty($transactions)) {
                // Get all transactions if none provided
                $allTransactions = Transaction::orderBy('transaction_time', 'desc')
                    ->orderBy('id', 'desc')
                    ->get();
                
                $transactions = $allTransactions->map(function ($transaction) {
                    return [
                        'requested_by' => $transaction->requested_by ?? 'N/A',
                        'approved_by' => $transaction->approved_by ?? 'N/A',
                        'borrower_name' => $transaction->borrower_name ?? 'N/A',
                        'location' => $transaction->location ?? 'N/A',
                        'item_name' => $transaction->item_name ?? 'N/A',
                        'quantity' => $transaction->quantity ?? 0,
                        'transaction_time' => $transaction->transaction_time ? $transaction->transaction_time->format('Y-m-d H:i:s') : 'N/A',
                        'status' => $transaction->status ?? 'Pending',
                    ];
                })->toArray();
            }
            
            // Generate HTML for PDF
            $html = $this->generatePdfHtml($transactions);
            
            // Configure DOMPDF
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');
            
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            
            $fileName = 'Transactions_' . date('Y-m-d_His') . '.pdf';
            
            return response()->streamDownload(function () use ($dompdf) {
                echo $dompdf->output();
            }, $fileName, [
                'Content-Type' => 'application/pdf',
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error exporting transactions to PDF: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to export transactions to PDF: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Generate HTML content for PDF
     */
    private function generatePdfHtml($transactions)
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
                <h2>TRANSACTIONS REPORT</h2>
                <p>For the Year ' . $currentYear . '</p>
                <p>Generated on: ' . $currentDate . '</p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Requested By</th>
                        <th>Approved By</th>
                        <th>Receiver</th>
                        <th>Location</th>
                        <th>Item Name</th>
                        <th>Quantity</th>
                        <th>Transaction Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($transactions as $transaction) {
            $transactionTime = $transaction['transaction_time'] ?? 'N/A';
            if ($transactionTime !== 'N/A') {
                try {
                    $date = new \DateTime($transactionTime);
                    $transactionTime = $date->format('m/d/Y H:i');
                } catch (\Exception $e) {
                    // Keep original format if parsing fails
                }
            }
            
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($transaction['requested_by'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($transaction['approved_by'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($transaction['borrower_name'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($transaction['location'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($transaction['item_name'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($transaction['quantity'] ?? 0) . '</td>
                        <td>' . htmlspecialchars($transactionTime) . '</td>
                        <td>' . htmlspecialchars($transaction['status'] ?? 'Pending') . '</td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
            <div class="footer">
                <p>Total Records: ' . count($transactions) . '</p>
                <p>End of Report</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
}

