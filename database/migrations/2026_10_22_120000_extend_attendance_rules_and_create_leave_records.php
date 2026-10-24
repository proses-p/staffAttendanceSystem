<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('expected_work_minutes')->nullable();
            $table->unsignedSmallInteger('grace_period_minutes')->default(0);
            $table->json('working_days')->nullable();
        });

        Schema::create('attendance_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('staff_leave_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leave_records');
        Schema::dropIfExists('attendance_holidays');

        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'expected_work_minutes',
                'grace_period_minutes',
                'working_days',
            ]);
        });
    }
};
