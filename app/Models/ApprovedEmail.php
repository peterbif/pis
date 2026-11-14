<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ApprovedEmail extends Model {protected $fillable=['email','created_by','used_at'];protected function casts():array{return ['used_at'=>'datetime'];}}
