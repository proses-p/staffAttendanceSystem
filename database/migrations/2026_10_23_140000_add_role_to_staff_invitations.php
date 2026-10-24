<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->string('role', 16)->default('staff')->after('email');
            $table->index(['company_id', 'role']);
        });
    }

    public function down(): void
    {
        if (DB::table('staff_invitations')->where('role', 'admin')->exists()) {
            throw new RuntimeException(
                'Cannot roll back invitation roles while company-admin invitations exist.'
            );
        }

        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'role']);
            $table->dropColumn('role');
        });
    }
};
