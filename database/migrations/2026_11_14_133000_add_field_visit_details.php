<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_assignments', function (Blueprint $table) {
            $table->string('client_or_service_name')->nullable()->after('task_type');
            $table->text('completion_notes')->nullable()->after('purpose_description');
        });
    }

    public function down(): void
    {
        Schema::table('field_assignments', function (Blueprint $table) {
            $table->dropColumn(['client_or_service_name', 'completion_notes']);
        });
    }
};
