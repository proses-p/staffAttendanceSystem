<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_leave_records', function (Blueprint $table) {
            $table->boolean('is_read')->default(false);
            $table->index(['company_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::table('staff_leave_records', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'is_read']);
            $table->dropColumn('is_read');
        });
    }
};
