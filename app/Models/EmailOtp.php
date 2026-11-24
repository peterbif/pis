<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EmailOtp extends Model {protected $fillable=['email','code_hash','password_hash','expires_at','attempts'];protected function casts():array{return ['expires_at'=>'datetime'];}}
