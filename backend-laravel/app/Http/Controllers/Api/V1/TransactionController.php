<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Exports\TransactionsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Get all transactions from transactions table
     */
    public function index(Request $request)
    {
        try {
            // Get all transactions from transactions table
            // Note: No need to load 'approver' relationship since approved_by now stores the name directly
            $transactions = Transaction::orderBy('transaction_time', 'desc')
                ->orderBy('id', 'desc')
                ->get();

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
}

