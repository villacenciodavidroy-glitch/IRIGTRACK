<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BorrowRequest;
use App\Models\Transaction;
use App\Models\Item;
use App\Models\User;

class MigrateBorrowRequestsToTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:migrate-borrow-requests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing approved and rejected borrow requests to transactions table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration of approved and rejected borrow requests to transactions table...');

        // Get all approved and rejected borrow requests
        $borrowRequests = BorrowRequest::whereIn('status', ['approved', 'rejected'])
            ->whereNotNull('approved_by')
            ->get();

        $this->info("Found {$borrowRequests->count()} borrow requests to migrate (approved and rejected).");

        $migrated = 0;
        $skipped = 0;

        foreach ($borrowRequests as $borrowRequest) {
            // Check if transaction already exists (to avoid duplicates)
            $existingTransaction = Transaction::where('approved_by', $borrowRequest->approved_by)
                ->where('borrower_name', $borrowRequest->borrowed_by)
                ->where('location', $borrowRequest->location)
                ->where('quantity', $borrowRequest->quantity)
                ->where('transaction_time', $borrowRequest->approved_at)
                ->first();

            if ($existingTransaction) {
                $skipped++;
                $this->line("Skipping borrow request #{$borrowRequest->id} - transaction already exists");
                continue;
            }

            // Get item details
            $item = null;
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $borrowRequest->item_id)) {
                $item = Item::where('uuid', $borrowRequest->item_id)->first();
            } else {
                $item = Item::where('id', $borrowRequest->item_id)->first();
            }

            // Use unit (item name) first, then description as fallback to match notifications
            $itemName = 'N/A';
            if ($item) {
                $itemName = $item->unit ?? $item->description ?? 'N/A';
            }

            // Get approver user to get their role and name
            $approver = User::find($borrowRequest->approved_by);
            $approverRole = $approver ? strtolower($approver->role ?? 'user') : 'user';
            
            // Get approver's full name (prioritize fullname, then username, then email)
            $approverName = 'N/A';
            if ($approver) {
                $approverName = $approver->fullname ?? $approver->username ?? $approver->email ?? 'N/A';
            }
            
            // Normalize and format role for database (uppercase to match frontend display)
            if (in_array($approverRole, ['admin', 'super_admin', 'superadmin'])) {
                $approverRole = 'ADMIN';
            } else {
                $approverRole = 'USER';
            }
            
            // Format status for database (capitalized to match frontend display)
            $status = strtolower($borrowRequest->status ?? 'pending');
            $formattedStatus = ucfirst($status); // "approved" -> "Approved", "rejected" -> "Rejected"

            try {
                // Create transaction record with formatted values matching frontend display
                // Store approver's full name directly in approved_by column to match database with system display
                Transaction::create([
                    'approved_by' => $approverName, // Store approver's full name directly (not ID)
                    'borrower_name' => $borrowRequest->borrowed_by,
                    'requested_by' => $borrowRequest->borrowed_by, // Person who requested the borrow
                    'location' => $borrowRequest->location,
                    'item_name' => $itemName,
                    'quantity' => $borrowRequest->quantity,
                    'transaction_time' => $borrowRequest->approved_at ?? $borrowRequest->created_at,
                    'role' => $approverRole, // Store as "ADMIN" or "USER" to match frontend
                    'status' => $formattedStatus, // Store as "Approved" or "Rejected" to match frontend
                ]);

                $migrated++;
                $this->line("Migrated borrow request #{$borrowRequest->id} -> Transaction created");
            } catch (\Exception $e) {
                $this->error("Failed to migrate borrow request #{$borrowRequest->id}: " . $e->getMessage());
            }
        }

        $this->info("\nMigration completed!");
        $this->info("Migrated: {$migrated} transactions");
        $this->info("Skipped: {$skipped} (already exist)");

        return Command::SUCCESS;
    }
}

