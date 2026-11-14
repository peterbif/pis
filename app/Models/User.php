<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = ['name', 'email', 'password', 'role', 'permissions', 'email_verified_at'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'permissions' => 'array'];
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'superadmin') return true;
        $permissions = $this->permissions ?? [];
        if ($permission === 'view_personnel' && array_intersect(['review_personnel', 'export_personnel'], $permissions)) return true;
        return in_array($permission, $permissions, true);
    }

    public function profile() { return $this->hasOne(PersonnelProfile::class); }
    public function documents() { return $this->hasMany(PersonnelDocument::class); }
    public function chatConversation() { return $this->hasOne(PersonnelConversation::class, 'personnel_id'); }
}
