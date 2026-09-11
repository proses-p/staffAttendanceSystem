<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_out_time');
            $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
            $table->decimal('check_out_distance', 10, 2)->nullable()->after('check_out_longitude');
            $table->string('sign_in_location')->nullable()->after('distance');
            $table->string('sign_out_location')->nullable()->after('check_out_distance');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'check_out_latitude',
                'check_out_longitude',
                'check_out_distance',
                'sign_in_location',
                'sign_out_location',
            ]);
        });
    }
};