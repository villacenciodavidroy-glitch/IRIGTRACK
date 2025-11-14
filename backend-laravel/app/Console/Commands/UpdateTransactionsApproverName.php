<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\User;

class UpdateTransactionsApproverName extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:update-approver-name';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing transactions with approver names from user records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating transactions with approver names...');

        $transactions = Transaction::all();
        $this->info("Found {$transactions->count()} transactions to update.");

        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($transactions as $transaction) {
            try {
                if (!$transaction->approved_by) {
                    $skipped++;
                    $this->line("Transaction #{$transaction->id} - No approver ID, skipping");
                    continue;
                }

                $approver = User::find($transaction->approved_by);

                if (!$approver) {
                    $notFound++;
                    $this->warn("Transaction #{$transaction->id} - Approver (ID: {$transaction->approved_by}) not found");
                    continue;
                }

                // Get approver's full name (prioritize fullname, then username, then email)
                $approverName = $approver->fullname ?? $approver->username ?? $approver->email ?? 'N/A';

                if ($transaction->approved_by_name !== $approverName) {
                    $transaction->approved_by_name = $approverName;
                    $transaction->save();
                    $updated++;
                    $this->line("Transaction #{$transaction->id} - Updated approver name to: '{$approverName}'");
                } else {
                    $skipped++;
                    $this->line("Transaction #{$transaction->id} - Approver name already correct: '{$approverName}'");
                }
            } catch (\Exception $e) {
                $this->error("Failed to update transaction #{$transaction->id}: " . $e->getMessage());
            }
        }

        $this->info("\nUpdate completed!");
        $this->info("Updated: {$updated} transactions");
        $this->info("Skipped: {$skipped} (already correct or no approver)");
        $this->info("Not found: {$notFound} (approver not found)");

        return Command::SUCCESS;
    }
}

