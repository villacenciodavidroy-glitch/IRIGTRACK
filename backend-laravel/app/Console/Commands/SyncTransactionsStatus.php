<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\BorrowRequest;
use App\Models\Item;

class SyncTransactionsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:sync-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync transaction status from borrow_requests table to ensure accuracy';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Syncing transaction statuses from borrow_requests table...');

        $transactions = Transaction::all();
        $this->info("Found {$transactions->count()} transactions to check.");

        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($transactions as $transaction) {
            try {
                // Try to find matching borrow request
                // Match by: approved_by, borrower_name (borrowed_by), location, quantity, and transaction_time (approved_at)
                $borrowRequest = BorrowRequest::where('approved_by', $transaction->approved_by)
                    ->where('borrowed_by', $transaction->borrower_name)
                    ->where('location', $transaction->location)
                    ->where('quantity', $transaction->quantity)
                    ->where(function($query) use ($transaction) {
                        if ($transaction->transaction_time) {
                            // Match by date/time (within 1 minute tolerance for timestamp differences)
                            $query->whereBetween('approved_at', [
                                $transaction->transaction_time->copy()->subMinute(),
                                $transaction->transaction_time->copy()->addMinute()
                            ]);
                        } else {
                            $query->whereNull('approved_at');
                        }
                    })
                    ->first();

                if (!$borrowRequest) {
                    // Try a looser match without transaction_time
                    $borrowRequest = BorrowRequest::where('approved_by', $transaction->approved_by)
                        ->where('borrowed_by', $transaction->borrower_name)
                        ->where('location', $transaction->location)
                        ->where('quantity', $transaction->quantity)
                        ->whereIn('status', ['approved', 'rejected'])
                        ->first();
                }

                if ($borrowRequest) {
                    $actualStatus = strtolower(trim($borrowRequest->status ?? 'pending'));
                    $currentStatus = strtolower(trim($transaction->status ?? 'pending'));

                    if ($actualStatus !== $currentStatus) {
                        $transaction->status = $actualStatus;
                        $transaction->save();
                        $updated++;
                        $this->line("Transaction #{$transaction->id} - Updated status from '{$currentStatus}' to '{$actualStatus}' (Borrow Request #{$borrowRequest->id})");
                    } else {
                        $skipped++;
                        $this->line("Transaction #{$transaction->id} - Status already correct: '{$actualStatus}'");
                    }
                } else {
                    $notFound++;
                    $this->warn("Transaction #{$transaction->id} - No matching borrow request found. Current status: '{$transaction->status}'");
                }
            } catch (\Exception $e) {
                $this->error("Failed to sync transaction #{$transaction->id}: " . $e->getMessage());
            }
        }

        $this->info("\nSync completed!");
        $this->info("Updated: {$updated} transactions");
        $this->info("Skipped: {$skipped} (already correct)");
        $this->info("Not found: {$notFound} (no matching borrow request)");

        return Command::SUCCESS;
    }
}

