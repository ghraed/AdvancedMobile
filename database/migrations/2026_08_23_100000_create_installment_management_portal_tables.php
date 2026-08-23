<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number')->unique();
            $table->foreignId('installment_application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('original_principal_cents');
            $table->unsignedBigInteger('financing_fee_cents')->default(0);
            $table->unsignedBigInteger('total_payable_cents');
            $table->unsignedTinyInteger('payment_count');
            $table->date('first_due_date');
            $table->string('account_status')->default('active');
            $table->unsignedBigInteger('amount_paid_cents')->default(0);
            $table->string('currency', 8)->default('USD');
            $table->timestamp('activated_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['account_status', 'first_due_date']);
            $table->index(['user_id', 'account_status']);
        });

        Schema::create('installment_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('installment_number');
            $table->date('due_date');
            $table->unsignedBigInteger('amount_due_cents');
            $table->unsignedBigInteger('amount_paid_cents')->default(0);
            $table->string('status')->default('upcoming');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['installment_account_id', 'installment_number'], 'schedule_account_number_unique');
            $table->index(['due_date', 'status']);
            $table->index(['installment_account_id', 'due_date']);
        });

        Schema::create('installment_payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('installment_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('schedule_item_id')->nullable()->constrained('installment_schedule_items')->nullOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('remaining_balance_after_cents');
            $table->string('payment_method');
            $table->string('reference')->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->unique(['installment_account_id', 'idempotency_key'], 'payments_account_idempotency_unique');
            $table->index(['installment_account_id', 'paid_at']);
            $table->index(['paid_at', 'reversed_at']);
        });

        Schema::create('installment_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_payment_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('installment_schedule_item_id');
            $table->index('installment_schedule_item_id', 'ipa_schedule_item_idx');
            $table->foreign('installment_schedule_item_id', 'ipa_schedule_item_fk')->references('id')->on('installment_schedule_items')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
            $table->unique(['installment_payment_id', 'installment_schedule_item_id'], 'payment_schedule_allocation_unique');
        });

        Schema::create('installment_account_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('note');
            $table->timestamps();
            $table->index(['installment_account_id', 'created_at'], 'ian_account_created_idx');
        });

        Schema::create('installment_account_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_account_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['installment_account_id', 'created_at'], 'iae_account_created_idx');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_account_events');
        Schema::dropIfExists('installment_account_notes');
        Schema::dropIfExists('installment_payment_allocations');
        Schema::dropIfExists('installment_payments');
        Schema::dropIfExists('installment_schedule_items');
        Schema::dropIfExists('installment_accounts');
    }
};
