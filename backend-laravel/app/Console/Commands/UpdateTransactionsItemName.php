<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\BorrowRequest;
use App\Models\Item;

class UpdateTransactionsItemName extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:update-item-name';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update transaction item names to use unit (item name) instead of description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating transaction item names to use unit field (item name)...');

        $transactions = Transaction::all();
        $this->info("Found {$transactions->count()} transactions to check.");

        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($transactions as $transaction) {
            try {
                // Try to find matching borrow request to get the item
                $borrowRequest = BorrowRequest::where('approved_by', $transaction->approved_by)
                    ->where('borrowed_by', $transaction->borrower_name)
                    ->where('location', $transaction->location)
                    ->where('quantity', $transaction->quantity)
                    ->where(function($query) use ($transaction) {
                        if ($transaction->transaction_time) {
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
                    // Get item details
                    $item = null;
                    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $borrowRequest->item_id)) {
                        $item = Item::where('uuid', $borrowRequest->item_id)->first();
                    } else {
                        $item = Item::where('id', $borrowRequest->item_id)->first();
                    }

                    if ($item) {
                        // Use unit (item name) first, then description as fallback
                        $correctItemName = $item->unit ?? $item->description ?? 'N/A';
                        
                        if ($transaction->item_name !== $correctItemName) {
                            $transaction->item_name = $correctItemName;
                            $transaction->save();
                            $updated++;
                            $this->line("Transaction #{$transaction->id} - Updated item name from '{$transaction->item_name}' to '{$correctItemName}'");
                        } else {
                            $skipped++;
                            $this->line("Transaction #{$transaction->id} - Item name already correct: '{$correctItemName}'");
                        }
                    } else {
                        $notFound++;
                        $this->warn("Transaction #{$transaction->id} - Item not found for borrow request #{$borrowRequest->id}");
                    }
                } else {
                    $notFound++;
                    $this->warn("Transaction #{$transaction->id} - No matching borrow request found. Current item name: '{$transaction->item_name}'");
                }
            } catch (\Exception $e) {
                $this->error("Failed to update transaction #{$transaction->id}: " . $e->getMessage());
            }
        }

        $this->info("\nUpdate completed!");
        $this->info("Updated: {$updated} transactions");
        $this->info("Skipped: {$skipped} (already correct)");
        $this->info("Not found: {$notFound} (no matching borrow request or item)");

        return Command::SUCCESS;
    }
}

