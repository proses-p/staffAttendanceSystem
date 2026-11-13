<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_work_submission_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_work_submission_id')
                ->constrained('daily_work_submissions')
                ->cascadeOnDelete();
            $table->string('photo_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_work_submission_photos');
    }
};
