<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PersonnelDocument extends Model
{
    protected $fillable = ['user_id','category','education_index','original_name','mime_type','size_bytes','storage_path'];
    protected $hidden = ['storage_path'];
    public function user(){return $this->belongsTo(User::class);}
}
