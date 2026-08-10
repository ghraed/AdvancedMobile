<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->index(['category_id', 'status', 'deleted_at'], 'products_category_status_deleted_index');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->index(['product_id', 'stock_quantity', 'is_active'], 'product_variants_product_stock_active_index');
        });

        Schema::table('installment_plans', function (Blueprint $table): void {
            $table->index(
                ['product_id', 'product_variant_id', 'number_of_payments', 'interval_type', 'is_active'],
                'installment_plans_scope_lookup_index'
            );
        });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->index(['product_id', 'sort_order', 'is_primary'], 'product_images_product_sort_primary_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $table->dropIndex('product_images_product_sort_primary_index');
        });

        Schema::table('installment_plans', function (Blueprint $table): void {
            $table->dropIndex('installment_plans_scope_lookup_index');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropIndex('product_variants_product_stock_active_index');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_category_status_deleted_index');
        });
    }
};
