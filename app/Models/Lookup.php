<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lookup extends Model
{
    protected $fillable = ['category', 'label', 'parent', 'active', 'sort_order'];

    
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
