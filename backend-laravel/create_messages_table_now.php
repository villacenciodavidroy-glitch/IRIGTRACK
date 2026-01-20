<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    if (Schema::hasTable('supply_request_messages')) {
        print "Table already exists!\n";
        exit(0);
    }
    
    print "Creating supply_request_messages table...\n";
    
    DB::unprepared("CREATE TABLE supply_request_messages (
        id BIGSERIAL PRIMARY KEY,
        supply_request_id BIGINT NOT NULL,
        user_id BIGINT NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN NOT NULL DEFAULT FALSE,
        read_at TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_supply_request_messages_supply_request 
            FOREIGN KEY (supply_request_id) 
            REFERENCES supply_requests(id) 
            ON DELETE CASCADE,
        CONSTRAINT fk_supply_request_messages_user 
            FOREIGN KEY (user_id) 
            REFERENCES users(id) 
            ON DELETE CASCADE
    )");
    
    print "Table created!\n";
    
    DB::unprepared("CREATE INDEX idx_supply_request_messages_supply_request_id ON supply_request_messages(supply_request_id)");
    DB::unprepared("CREATE INDEX idx_supply_request_messages_user_id ON supply_request_messages(user_id)");
    DB::unprepared("CREATE INDEX idx_supply_request_messages_is_read ON supply_request_messages(is_read)");
    DB::unprepared("CREATE INDEX idx_supply_request_messages_created_at ON supply_request_messages(created_at)");
    
    print "Indexes created!\n";
    print "SUCCESS! Table supply_request_messages created with " . count(Schema::getColumnListing('supply_request_messages')) . " columns.\n";
    
} catch (\Exception $e) {
    print "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
