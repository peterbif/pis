<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::table('personnel_profiles',function(Blueprint $t){$t->string('file_number')->nullable()->index();$t->string('ippis_number')->nullable()->index();$t->string('surname')->nullable()->index();$t->string('first_name')->nullable()->index();});}
 public function down():void{Schema::table('personnel_profiles',function(Blueprint $t){$t->dropIndex(['file_number']);$t->dropIndex(['ippis_number']);$t->dropIndex(['surname']);$t->dropIndex(['first_name']);$t->dropColumn(['file_number','ippis_number','surname','first_name']);});}
};
