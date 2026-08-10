<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_purchase_sessions', function (Blueprint $table): void {
            $table->timestamp('scheduled_at')->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('pending_purchase_sessions', function (Blueprint $table): void {
            $table->dropColumn('scheduled_at');
        });
    }
};
