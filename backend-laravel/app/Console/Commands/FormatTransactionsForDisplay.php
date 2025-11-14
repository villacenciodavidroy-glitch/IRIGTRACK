<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\User;

class FormatTransactionsForDisplay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:format-for-display';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Format existing transactions to match frontend display (ADMIN/USER for role, Approved/Rejected for status)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Formatting transactions to match frontend display...');

        $transactions = Transaction::all();
        $this->info("Found {$transactions->count()} transactions to format.");

        $updated = 0;
        $skipped = 0;

        foreach ($transactions as $transaction) {
            try {
                $needsUpdate = false;
                
                // Format role: convert to uppercase (ADMIN or USER)
                $currentRole = strtolower(trim($transaction->role ?? 'user'));
                $formattedRole = 'USER';
                if (in_array($currentRole, ['admin', 'super_admin', 'superadmin'])) {
                    $formattedRole = 'ADMIN';
                }
                
                if ($transaction->role !== $formattedRole) {
                    $transaction->role = $formattedRole;
                    $needsUpdate = true;
                }
                
                // Format status: capitalize first letter (Approved, Rejected, Pending)
                $currentStatus = strtolower(trim($transaction->status ?? 'pending'));
                $formattedStatus = ucfirst($currentStatus); // "approved" -> "Approved", "rejected" -> "Rejected"
                
                if ($transaction->status !== $formattedStatus) {
                    $transaction->status = $formattedStatus;
                    $needsUpdate = true;
                }
                
                if ($needsUpdate) {
                    $transaction->save();
                    $updated++;
                    $this->line("Transaction #{$transaction->id} - Updated role: '{$formattedRole}', status: '{$formattedStatus}'");
                } else {
                    $skipped++;
                    $this->line("Transaction #{$transaction->id} - Already formatted correctly");
                }
            } catch (\Exception $e) {
                $this->error("Failed to format transaction #{$transaction->id}: " . $e->getMessage());
            }
        }

        $this->info("\nFormatting completed!");
        $this->info("Updated: {$updated} transactions");
        $this->info("Skipped: {$skipped} (already formatted)");

        return Command::SUCCESS;
    }
}

