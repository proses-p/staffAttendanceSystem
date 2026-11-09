<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_assignments', function (Blueprint $table) {
            // 1. Tengeneza column ya clock_out_at KWANZA ili iwepo kwenye table
            $table->timestamp('clock_out_at')->nullable();

            // 2. Sasa weka index kwa sababu columns zote (company_id, user_id, clock_out_at) zipo tayari
            $table->index(['company_id', 'user_id', 'clock_out_at']);

            $table->decimal('clock_in_lat', 10, 7)->nullable();
            $table->decimal('clock_in_lng', 10, 7)->nullable();
            $table->decimal('clock_out_lat', 10, 7)->nullable();
            $table->decimal('clock_out_lng', 10, 7)->nullable();
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->float('gps_accuracy')->nullable();
            $table->string('device_uuid', 128)->nullable();
            $table->boolean('is_mock_location')->default(false);
            $table->boolean('is_flagged')->default(false)->index();
            $table->json('flag_reasons')->nullable();
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->boolean('is_offline')->default(false);
            $table->string('time_source', 16)->default('server');
            $table->timestamp('client_device_time')->nullable();
            $table->timestamp('synced_at')->nullable();
        });

        Schema::table('field_assignments', function (Blueprint $table) {
            $table->string('task_type', 32)->change();
        });

        Schema::create('client_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_assignment_id')->constrained('field_assignments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('client_name');
            $table->text('notes')->nullable();
            $table->timestamp('arrived_at');
            $table->decimal('arrived_lat', 10, 7);
            $table->decimal('arrived_lng', 10, 7);
            $table->float('arrived_accuracy')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->decimal('departed_lat', 10, 7)->nullable();
            $table->decimal('departed_lng', 10, 7)->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->uuid('client_uuid')->nullable()->unique();
            $table->boolean('is_offline')->default(false);
            $table->string('time_source', 16)->default('server');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'arrived_at']);
        });

        Schema::create('processed_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('field_assignments')->where('task_type', 'innovation_week')->exists()) {
            throw new RuntimeException(
                'Cannot roll back field operations while innovation_week assignments exist.'
            );
        }

        Schema::dropIfExists('processed_events');
        Schema::dropIfExists('client_visits');

        Schema::table('field_assignments', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropUnique(['client_uuid']);
            $table->dropIndex(['is_flagged']);
            $table->dropIndex(['company_id', 'user_id', 'clock_out_at']);
            $table->dropColumn([
                'clock_in_lat',
                'clock_in_lng',
                'clock_out_at',
                'clock_out_lat',
                'clock_out_lng',
                'last_lat',
                'last_lng',
                'last_location_at',
                'gps_accuracy',
                'device_uuid',
                'is_mock_location',
                'is_flagged',
                'flag_reasons',
                'risk_score',
                'reviewed_by',
                'reviewed_at',
                'client_uuid',
                'is_offline',
                'time_source',
                'client_device_time',
                'synced_at',
            ]);
        });

        Schema::table('field_assignments', function (Blueprint $table) {
            $table->enum('task_type', ['marketing', 'research', 'client_meeting', 'innovations'])->change();
        });
    }
};
