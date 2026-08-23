<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_price_cents')->nullable()->after('compare_at_price');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Null means unknown cost. It must never be silently interpreted as a known zero cost.
            $table->unsignedBigInteger('unit_cost_cents')->nullable()->after('unit_price_cents');
            $table->foreignId('category_id')->nullable()->after('product_variant_id')->constrained()->nullOnDelete();
            $table->string('category_name')->nullable()->after('brand');
            $table->index(['product_id', 'created_at']);
            $table->index(['category_id', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'payment_status', 'created_at'], 'orders_profit_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_profit_status_date_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['product_id', 'created_at']);
            $table->dropIndex(['category_id', 'created_at']);
            $table->dropColumn(['unit_cost_cents', 'category_id', 'category_name']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('cost_price_cents');
        });
    }
};
