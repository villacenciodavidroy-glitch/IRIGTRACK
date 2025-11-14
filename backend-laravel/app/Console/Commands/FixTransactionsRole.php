<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FixTransactionsRole extends Command
{
    protected $signature = 'transactions:fix-role';
    protected $description = 'Fix transaction roles by checking actual approver role from users table';

    public function handle()
    {
        $this->info('Checking and fixing transaction roles from database...');

        $transactions = Transaction::all();
        $this->info("Found {$transactions->count()} transactions to check.");

        $updated = 0;

        foreach ($transactions as $transaction) {
            try {
                // Get approver from database
                $approver = User::find($transaction->approved_by);
                
                if (!$approver) {
                    $this->warn("Transaction #{$transaction->id} - Approver ID {$transaction->approved_by} not found");
                    continue;
                }

                // Get actual role from user table
                $actualRole = strtolower(trim($approver->role ?? 'user'));
                
                // Normalize: admin, super_admin, superadmin -> admin
                if (in_array($actualRole, ['admin', 'super_admin', 'superadmin'])) {
                    $actualRole = 'admin';
                } else {
                    $actualRole = 'user';
                }

                // Check current stored role
                $currentRole = strtolower(trim($transaction->role ?? 'user'));
                
                $this->line("Transaction #{$transaction->id}:");
                $this->line("  Approver: {$approver->fullname} ({$approver->email})");
                $this->line("  Approver's actual role in users table: {$approver->role}");
                $this->line("  Normalized role: {$actualRole}");
                $this->line("  Current transaction role: {$transaction->role}");
                
                // Update if different
                if ($currentRole !== $actualRole) {
                    $transaction->role = $actualRole;
                    $transaction->save();
                    $updated++;
                    $this->info("  ✓ UPDATED: Role changed from '{$currentRole}' to '{$actualRole}'");
                } else {
                    $this->line("  - No change needed");
                }
                $this->line("");
                
            } catch (\Exception $e) {
                $this->error("Error processing transaction #{$transaction->id}: " . $e->getMessage());
            }
        }

        $this->info("\nFix completed!");
        $this->info("Updated: {$updated} transactions");

        return Command::SUCCESS;
    }
}

