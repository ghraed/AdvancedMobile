<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('option_signature');
            $table->timestamps();

            $table->unique('sku');
            $table->unique(['product_id', 'option_signature']);
            $table->index('product_id');
            $table->index('is_active');
            $table->index('stock_quantity');
            $table->index(['product_id', 'is_active', 'stock_quantity']);
        });

        Schema::create('product_variant_option_value', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_variant_id', 'product_option_value_id'], 'variant_option_value_unique');
            $table->index(['product_option_value_id', 'product_variant_id'], 'option_value_variant_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_option_value');
        Schema::dropIfExists('product_variants');
    }
};
