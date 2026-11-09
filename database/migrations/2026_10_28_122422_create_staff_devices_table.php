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
        Schema::create('staff_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('device_name')->nullable();
            $table->string('platform', 20)->default('android');
            $table->text('public_key'); // only public key is the one stored in the server
            $table->string('status', 20)->default('pending'); // pending, active, revorked
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('revorked_at')->nullable();
            $table->timestamps();

            // improve fasternes of the application by implementing index
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_devices');
    }
};
