<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PersonnelProfile extends Model {protected $fillable=['user_id','file_number','ippis_number','surname','first_name','data','status']; protected function casts():array{return ['data'=>'array'];} public function user(){return $this->belongsTo(User::class);} public function getRouteKeyName():string{return 'id';}}
