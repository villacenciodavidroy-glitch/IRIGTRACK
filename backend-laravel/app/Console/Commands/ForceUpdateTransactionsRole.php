<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\User;

class ForceUpdateTransactionsRole extends Command
{
    protected $signature = 'transactions:force-update-role';
    protected $description = 'Force update all transaction roles from approver user records';

    public function handle()
    {
        $this->info('Force updating all transaction roles from approver records...');

        $transactions = Transaction::all();
        $this->info("Found {$transactions->count()} transactions to update.");

        $updated = 0;
        $errors = 0;

        foreach ($transactions as $transaction) {
            try {
                // Get approver from database
                $approver = User::find($transaction->approved_by);
                
                if (!$approver) {
                    $this->warn("Transaction #{$transaction->id} - Approver ID {$transaction->approved_by} not found, setting to 'user'");
                    $transaction->role = 'user';
                    $transaction->save();
                    $updated++;
                    continue;
                }

                // Get actual role from user table
                $approverRole = strtolower(trim($approver->role ?? 'user'));
                
                // Normalize: admin, super_admin, superadmin -> admin
                if (in_array($approverRole, ['admin', 'super_admin', 'superadmin'])) {
                    $correctRole = 'admin';
                } else {
                    $correctRole = 'user';
                }

                // Force update the role
                $transaction->role = $correctRole;
                $transaction->save();
                
                $updated++;
                $this->line("Transaction #{$transaction->id} - Updated role to '{$correctRole}' (Approver: {$approver->fullname}, Role in DB: {$approver->role})");
                
            } catch (\Exception $e) {
                $errors++;
                $this->error("Error updating transaction #{$transaction->id}: " . $e->getMessage());
            }
        }

        $this->info("\nForce update completed!");
        $this->info("Updated: {$updated} transactions");
        if ($errors > 0) {
            $this->warn("Errors: {$errors} transactions");
        }

        return Command::SUCCESS;
    }
}

