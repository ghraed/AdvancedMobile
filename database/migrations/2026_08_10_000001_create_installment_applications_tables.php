<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('installment_applications')) {
            Schema::create('installment_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('application_number')->unique();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('phone', 20);
                $table->string('email');
                $table->text('address');
                $table->string('identity_document_type');
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
                $table->string('product_name_snapshot');
                $table->string('product_sku_snapshot')->nullable();
                $table->string('brand_snapshot')->nullable();
                $table->string('storage_snapshot')->nullable();
                $table->string('color_snapshot')->nullable();
                $table->decimal('product_price', 12, 2);
                $table->unsignedTinyInteger('installment_months');
                $table->decimal('monthly_payment', 12, 2);
                $table->decimal('total_payable', 12, 2);
                $table->string('currency', 8);
                $table->string('status')->default('submitted');
                $table->text('admin_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
                $table->index(['phone', 'created_at']);
            });
        }

        if (! Schema::hasTable('installment_application_documents')) {
            Schema::create('installment_application_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('installment_application_id');
                $table->foreign('installment_application_id', 'iad_application_fk')->references('id')->on('installment_applications')->cascadeOnDelete();
                $table->string('type');
                $table->string('original_filename');
                $table->string('stored_path');
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('size');
                $table->timestamp('uploaded_at');
                $table->timestamps();
                $table->unique(['installment_application_id', 'type'], 'iad_application_type_uq');
            });
        }

        if (! Schema::hasTable('installment_application_status_histories')) {
            Schema::create('installment_application_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('installment_application_id');
                $table->foreign('installment_application_id', 'iash_application_fk')->references('id')->on('installment_applications')->cascadeOnDelete();
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->text('note')->nullable();
                $table->foreignId('performed_by')->nullable();
                $table->foreign('performed_by', 'iash_performer_fk')->references('id')->on('users')->nullOnDelete();
                $table->timestamp('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_application_status_histories');
        Schema::dropIfExists('installment_application_documents');
        Schema::dropIfExists('installment_applications');
    }
};
