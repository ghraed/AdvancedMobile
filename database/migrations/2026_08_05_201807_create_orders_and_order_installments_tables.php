<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('pending_purchase_session_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->nullOnDelete();
            $table->foreignId('installment_plan_id')->nullable()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status')->default('pending');
            $table->string('product_name');
            $table->string('sku');
            $table->string('storage')->nullable();
            $table->string('color')->nullable();
            $table->decimal('variant_price', 12, 2);
            $table->decimal('financing_fee', 12, 2);
            $table->decimal('amount_due_today', 12, 2);
            $table->decimal('total_financed_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->unsignedInteger('future_payment_count');
            $table->string('interval_type');
            $table->json('customer_snapshot');
            $table->timestamps();
        });

        Schema::create('order_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['order_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_installments');
        Schema::dropIfExists('orders');
    }
};
