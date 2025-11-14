<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\User;

class UpdateTransactionsRoleStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:update-role-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing transactions with role and status based on approver and borrow request status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating transactions with correct role and status from database...');

        // Get ALL transactions to verify and update
        $transactions = Transaction::all();

        $this->info("Found {$transactions->count()} transactions to verify and update.");

        $updated = 0;
        $skipped = 0;

        foreach ($transactions as $transaction) {
            try {
                // Get approver user to get their ACTUAL role from database
                $approver = User::find($transaction->approved_by);
                
                if (!$approver) {
                    $this->warn("Transaction #{$transaction->id} - Approver (ID: {$transaction->approved_by}) not found, skipping...");
                    $skipped++;
                    continue;
                }
                
                // Get the actual role from the user record
                $approverRole = strtolower(trim($approver->role ?? 'user'));
                
                // Normalize role values
                if (in_array($approverRole, ['admin', 'super_admin', 'superadmin'])) {
                    $approverRole = 'admin';
                } else {
                    $approverRole = 'user';
                }
                
                // Determine status - if approved_by exists, it's approved (unless we check borrow_requests)
                // For now, we'll check if status is already set, otherwise default to approved
                $status = $transaction->status ?? 'approved';
                
                // Only update if role or status has changed
                $needsUpdate = false;
                if ($transaction->role !== $approverRole) {
                    $transaction->role = $approverRole;
                    $needsUpdate = true;
                }
                if ($transaction->status !== $status) {
                    $transaction->status = $status;
                    $needsUpdate = true;
                }
                
                if ($needsUpdate) {
                    $transaction->save();
                    $updated++;
                    $this->line("Updated transaction #{$transaction->id} - Approver: {$approver->fullname} ({$approver->email}), Role: {$approverRole}, Status: {$status}");
                } else {
                    $skipped++;
                    $this->line("Transaction #{$transaction->id} - Already correct (Role: {$approverRole}, Status: {$status})");
                }
            } catch (\Exception $e) {
                $this->error("Failed to update transaction #{$transaction->id}: " . $e->getMessage());
            }
        }

        $this->info("\nUpdate completed!");
        $this->info("Updated: {$updated} transactions");
        $this->info("Skipped: {$skipped} transactions (already correct or no approver)");

        return Command::SUCCESS;
    }
}

