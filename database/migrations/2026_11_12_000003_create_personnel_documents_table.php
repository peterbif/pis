<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void{Schema::create('personnel_documents',function(Blueprint $table){$table->id();$table->foreignId('user_id')->constrained()->cascadeOnDelete();$table->string('category',32);$table->unsignedSmallInteger('education_index')->nullable();$table->string('original_name');$table->string('mime_type',100);$table->unsignedInteger('size_bytes');$table->string('storage_path')->unique();$table->timestamps();$table->index(['user_id','category']);});}
    public function down():void{Schema::dropIfExists('personnel_documents');}
};
