<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('barcode')->nullable()->unique()->after('sku');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('sales_channel')->default('online')->index()->after('reference');
            $table->foreignId('cashier_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('cashier_name')->nullable()->after('cashier_id');
            $table->string('idempotency_key')->nullable()->unique()->after('pending_purchase_session_id');
            $table->unsignedBigInteger('subtotal_cents')->nullable()->after('customer_snapshot');
            $table->string('discount_type')->nullable()->after('subtotal_cents');
            $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
            $table->unsignedBigInteger('discount_cents')->default(0)->after('discount_value');
            $table->unsignedBigInteger('total_cents')->nullable()->after('discount_cents');
            $table->string('payment_status')->default('unpaid')->index()->after('total_cents');
            $table->timestamp('refunded_at')->nullable()->after('payment_status');
            $table->index(['sales_channel', 'status', 'created_at']);
            $table->index(['sales_channel', 'cashier_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('brand')->nullable();
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->json('variant_options')->nullable();
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->timestamps();
            $table->index(['product_variant_id', 'created_at']);
            $table->index(['sku', 'created_at']);
        });

        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method');
            $table->unsignedBigInteger('amount_cents');
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('cash_received_cents')->nullable();
            $table->unsignedBigInteger('change_due_cents')->default(0);
            $table->string('status')->default('completed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['order_id', 'status']);
            $table->index(['payment_method', 'created_at']);
        });

        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('reference')->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->text('reason');
            $table->json('restored_items');
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('refunded_by_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('order_items');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['cashier_id']);
            $table->dropIndex(['sales_channel', 'status', 'created_at']);
            $table->dropIndex(['sales_channel', 'cashier_id', 'created_at']);
            $table->dropIndex(['sales_channel']);
            $table->dropIndex(['payment_status']);
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn([
                'sales_channel', 'cashier_id', 'cashier_name', 'idempotency_key',
                'subtotal_cents', 'discount_type', 'discount_value', 'discount_cents',
                'total_cents', 'payment_status', 'refunded_at',
            ]);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropColumn('barcode');
        });
    }
};
