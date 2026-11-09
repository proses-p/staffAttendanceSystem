<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_devices', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('public_key_fingerprint', 64)->nullable()->unique();
        });

        DB::table('staff_devices')->whereNotNull('revorked_at')->update([
            'revoked_at' => DB::raw('revorked_at'),
            'status' => 'revoked',
        ]);
        DB::table('staff_devices')->where('status', 'revorked')->update(['status' => 'revoked']);

        DB::table('staff_devices')->select(['id', 'public_key'])->orderBy('id')->chunk(100, function ($devices): void {
            foreach ($devices as $device) {
                DB::table('staff_devices')->where('id', $device->id)->update([
                    'public_key_fingerprint' => hash('sha256', $device->public_key),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropUnique(['public_key_fingerprint']);
            $table->dropColumn('public_key_fingerprint');
            $table->dropColumn('revoked_at');
        });
    }
};
