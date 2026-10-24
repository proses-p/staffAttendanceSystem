<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'admin', 'staff'])
                ->default('staff')
                ->change();
        });

        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->unique('company_id', 'attendance_settings_company_unique');
        });

        Schema::table('attendance_holidays', function (Blueprint $table) {
            $table->dropUnique('attendance_holidays_holiday_date_unique');
            $table->unique(
                ['company_id', 'holiday_date'],
                'attendance_holidays_company_date_unique'
            );
        });
    }

    public function down(): void
    {
        if (DB::table('users')->where('role', 'super_admin')->exists()) {
            throw new RuntimeException(
                'Cannot roll back the role migration while super_admin accounts exist.'
            );
        }

        Schema::table('attendance_holidays', function (Blueprint $table) {
            $table->dropUnique('attendance_holidays_company_date_unique');
            $table->unique('holiday_date', 'attendance_holidays_holiday_date_unique');
        });

        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropUnique('attendance_settings_company_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'staff'])
                ->default('staff')
                ->change();
        });
    }
};
