<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('users', function(Blueprint $t){$t->id();$t->string('name')->nullable();$t->string('email')->unique();$t->timestamp('email_verified_at')->nullable();$t->string('password');$t->string('role')->default('personnel');$t->rememberToken();$t->timestamps();});
  Schema::create('personal_access_tokens',function(Blueprint $t){$t->id();$t->morphs('tokenable');$t->string('name');$t->string('token',64)->unique();$t->text('abilities')->nullable();$t->timestamp('last_used_at')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamps();});
  Schema::create('approved_emails',function(Blueprint $t){$t->id();$t->string('email')->unique();$t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('used_at')->nullable();$t->timestamps();});
  Schema::create('email_otps',function(Blueprint $t){$t->id();$t->string('email')->index();$t->string('code_hash');$t->string('password_hash')->nullable();$t->timestamp('expires_at');$t->unsignedTinyInteger('attempts')->default(0);$t->timestamps();});
  Schema::create('personnel_profiles',function(Blueprint $t){$t->id();$t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();$t->json('data')->nullable();$t->string('status')->default('incomplete');$t->timestamps();});
  Schema::create('lookups',function(Blueprint $t){$t->id();$t->string('category')->index();$t->string('label');$t->string('parent')->nullable();$t->boolean('active')->default(true);$t->unsignedInteger('sort_order')->default(0);$t->timestamps();$t->unique(['category','label','parent']);});
 }
 public function down(): void {foreach(['lookups','personnel_profiles','email_otps','approved_emails','personal_access_tokens','users'] as $table) Schema::dropIfExists($table);}
};
