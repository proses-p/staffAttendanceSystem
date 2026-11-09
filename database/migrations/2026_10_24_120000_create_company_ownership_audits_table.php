<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_ownership_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('record_type', 64);
            $table->unsignedBigInteger('record_id');
            $table->string('operator', 128);
            $table->timestamp('created_at');
            $table->index(['company_id', 'created_at']);
            $table->index(['record_type', 'record_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('company_ownership_audits')->exists()) {
            throw new RuntimeException('Cannot roll back the ownership audit table while audit records exist.');
        }

        Schema::dropIfExists('company_ownership_audits');
    }
};
