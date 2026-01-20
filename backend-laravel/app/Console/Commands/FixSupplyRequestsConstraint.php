<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixSupplyRequestsConstraint extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supply-requests:fix-constraint';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix the supply_requests status constraint to include new statuses';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fixing supply_requests status constraint...');
        
        try {
            // Drop old constraint
            $this->info('Step 1: Dropping old constraint...');
            DB::statement("ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS check_status");
            $this->info('✓ Old constraint dropped');
            
            // Add new constraint
            $this->info('Step 2: Adding new constraint...');
            DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'rejected', 'fulfilled'))");
            $this->info('✓ New constraint added');
            
            // Verify
            $this->info('Step 3: Verifying constraint...');
            $result = DB::select("SELECT check_clause FROM information_schema.check_constraints WHERE constraint_name = 'check_status' AND constraint_schema = 'public'");
            if (!empty($result)) {
                $this->info('✓ Constraint verified: ' . $result[0]->check_clause);
            }
            
            $this->info('');
            $this->info('=== SUCCESS! Constraint has been fixed ===');
            $this->info('You can now approve supply requests.');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('ERROR: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile());
            $this->error('Line: ' . $e->getLine());
            
            $this->info('');
            $this->info('Please run the SQL directly in PostgreSQL:');
            $this->info('ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS check_status;');
            $this->info("ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'rejected', 'fulfilled'));");
            
            return Command::FAILURE;
        }
    }
}

