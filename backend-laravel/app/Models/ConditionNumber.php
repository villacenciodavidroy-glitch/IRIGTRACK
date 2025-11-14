<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConditionNumber extends Model
{
    use HasFactory;

    protected $fillable = ['condition_number', 'condition_status'];

    public function item()
    {
        return $this->hasMany(Item::class);
    }
}
