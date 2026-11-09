<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_leave_records', function (Blueprint $table) {
            $table->string('leave_type')->default('Leave');
            $table->text('reason')->nullable();
            $table->text('decision_note')->nullable();
            $table->string('request_source')->default('admin_record');
        });
    }

    public function down(): void
    {
        Schema::table('staff_leave_records', function (Blueprint $table) {
            $table->dropColumn([
                'leave_type',
                'reason',
                'decision_note',
                'request_source',
            ]);
        });
    }
};
