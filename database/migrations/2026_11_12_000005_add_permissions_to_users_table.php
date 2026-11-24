<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('role');
        });

        DB::table('users')->where('role', 'admin')->whereNull('permissions')->update([
            'permissions' => json_encode([
                'view_dashboard', 'view_personnel', 'review_personnel', 'export_personnel',
                'manage_invitations', 'manage_lookups',
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
