<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('variant_key')->nullable();
            $table->unsignedTinyInteger('number_of_payments');
            $table->decimal('down_payment', 12, 2)->default(0);
            $table->decimal('financing_fee', 12, 2)->default(0);
            $table->decimal('installment_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('interval_type')->default('monthly');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
            $table->index(['product_variant_id', 'is_active']);
            $table->index(['number_of_payments', 'interval_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_plans');
    }
};
