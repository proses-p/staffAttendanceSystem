<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDeleteCascade(); // the attendanc belongs to the specific user
            $table->date('attendance_date');
            $table->time('check_in_time');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('distance', 10, 2);
            $table->enum('status', ['present'])->default('present');
            $table->timestamps();
            // one user cannot belong to more than one attendance in one day.
            $table->unique([
                'user_id',
                'attendance_date',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
