<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonnelConversation extends Model
{
    protected $fillable = ['personnel_id', 'admin_last_read_at', 'personnel_last_read_at'];

    protected function casts(): array
    {
        return ['admin_last_read_at' => 'datetime', 'personnel_last_read_at' => 'datetime'];
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'personnel_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PersonnelMessage::class, 'conversation_id');
    }
}
