<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\User;

class UpdateApprovedByToName extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:update-approved-by-to-name';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update approved_by column from ID to full name for all existing transactions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating approved_by column from ID to full name...');

        $transactions = Transaction::all();
        $this->info("Found {$transactions->count()} transactions to update.");

        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($transactions as $transaction) {
            try {
                // Check if approved_by is already a name (string) or still an ID (numeric)
                if (is_numeric($transaction->approved_by)) {
                    $approverId = (int) $transaction->approved_by;
                    $approver = User::find($approverId);

                    if (!$approver) {
                        $notFound++;
                        $this->warn("Transaction #{$transaction->id} - Approver (ID: {$approverId}) not found");
                        continue;
                    }

                    // Get approver's full name (prioritize fullname, then username, then email)
                    $approverName = $approver->fullname ?? $approver->username ?? $approver->email ?? 'N/A';

                    $transaction->approved_by = $approverName;
                    $transaction->save();
                    $updated++;
                    $this->line("Transaction #{$transaction->id} - Updated approved_by from ID {$approverId} to name: '{$approverName}'");
                } else {
                    $skipped++;
                    $this->line("Transaction #{$transaction->id} - approved_by already contains name: '{$transaction->approved_by}'");
                }
            } catch (\Exception $e) {
                $this->error("Failed to update transaction #{$transaction->id}: " . $e->getMessage());
            }
        }

        $this->info("\nUpdate completed!");
        $this->info("Updated: {$updated} transactions");
        $this->info("Skipped: {$skipped} (already contains name)");
        $this->info("Not found: {$notFound} (approver not found)");

        return Command::SUCCESS;
    }
}

