<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TENANT_TABLES = [
        'users',
        'office_locations',
        'attendance_settings',
        'attendance_holidays',
        'staff_invitations',
        'staff_leave_records',
        'attendances',
    ];

    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 16)->default('active');
            $table->index('status');
            $table->timestamps();
        });

        foreach (self::TENANT_TABLES as $tenantTable) {
            Schema::table($tenantTable, function (Blueprint $table) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->constrained('companies');
                $table->index('company_id');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TENANT_TABLES) as $tenantTable) {
            Schema::table($tenantTable, function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropIndex(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        Schema::dropIfExists('companies');
    }
};
