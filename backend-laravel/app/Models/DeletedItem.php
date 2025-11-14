<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeletedItem extends Model
{
    protected $table = 'deleted_items';
    
    protected $primaryKey = 'deleted_id';
    
    public $timestamps = false; // created_at is managed manually
    
    protected $fillable = [
        'item_id',
        'reason_for_deletion',
        'user_id'
    ];
    
    protected $casts = [
        'created_at' => 'datetime'
    ];
    
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id')->withTrashed();
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
