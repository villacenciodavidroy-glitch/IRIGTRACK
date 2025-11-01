<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;

class ActivityLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some users to create activity logs for
        $users = User::take(5)->get();
        
        if ($users->isEmpty()) {
            $this->command->info('No users found. Please run user seeder first.');
            return;
        }

        $actions = ['Logged In', 'Logged Out', 'Item Borrowed', 'Item Returned', 'Profile Updated'];
        
        // Create activity logs for the last 30 days
        for ($i = 0; $i < 50; $i++) {
            $user = $users->random();
            $action = $actions[array_rand($actions)];
            $createdAt = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            
            ActivityLog::create([
                'user_id' => $user->id,
                'action' => $action,
                'description' => $this->getDescriptionForAction($action),
                'ip_address' => '192.168.1.' . rand(1, 255),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
        
        $this->command->info('Activity logs seeded successfully!');
    }
    
    private function getDescriptionForAction($action)
    {
        $descriptions = [
            'Logged In' => 'User successfully logged into the system',
            'Logged Out' => 'User logged out of the system',
            'Item Borrowed' => 'User borrowed an item from inventory',
            'Item Returned' => 'User returned a borrowed item',
            'Profile Updated' => 'User updated their profile information'
        ];
        
        return $descriptions[$action] ?? 'User performed an action';
    }
}