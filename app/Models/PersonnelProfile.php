<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelProfile extends Model
{
    protected $fillable = ['user_id', 'file_number', 'ippis_number', 'surname', 'first_name', 'date_of_first_appointment', 'data', 'status'];
    protected function casts(): array
    {
        return ['data' => 'array', 'date_of_first_appointment' => 'date:Y-m-d'];
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
