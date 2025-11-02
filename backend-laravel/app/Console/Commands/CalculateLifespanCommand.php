<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CalculateLifespanJob;

class CalculateLifespanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'items:calculate-lifespan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and update remaining_years for all items using Python API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting lifespan calculation...');
        
        try {
            $job = new CalculateLifespanJob();
            $job->handle();
            
            $this->info('✅ Lifespan calculation completed successfully!');
            $this->info('Check logs for detailed information.');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error calculating lifespan: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }
}

