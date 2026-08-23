<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('is_unit_managed')->default(false)->index()->after('stock_quantity');
        });

        Schema::create('device_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('condition_type', 24)->index();
            $table->string('condition_grade', 8)->nullable()->index();
            $table->text('imei_encrypted');
            $table->char('imei_hash', 64)->unique();
            $table->text('serial_number_encrypted')->nullable();
            $table->char('serial_number_hash', 64)->nullable()->index();
            $table->unsignedTinyInteger('battery_health_percent')->nullable()->index();
            $table->text('cosmetic_condition')->nullable();
            $table->text('customer_visible_condition_notes')->nullable();
            $table->json('known_defects')->nullable();
            $table->json('condition_checklist')->nullable();
            $table->json('included_accessories')->nullable();
            $table->date('refurbished_at')->nullable();
            $table->string('refurbished_by')->nullable();
            $table->json('parts_replaced')->nullable();
            $table->text('customer_visible_refurbishment_details')->nullable();
            $table->text('refurbishment_notes')->nullable();
            $table->unsignedInteger('warranty_days')->nullable();
            $table->date('warranty_until')->nullable();
            $table->unsignedBigInteger('acquisition_cost_cents')->nullable();
            $table->unsignedBigInteger('selling_price_override_cents')->nullable();
            $table->boolean('installments_enabled')->default(false);
            $table->string('status', 24)->default('repair')->index();
            $table->char('reservation_token_hash', 64)->nullable()->index();
            $table->timestamp('reserved_until')->nullable()->index();
            $table->timestamps();
            $table->index(['product_variant_id', 'status']);
            $table->index(['condition_type', 'status']);
            $table->index(['condition_grade', 'battery_health_percent']);
        });

        Schema::create('device_unit_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_unit_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->string('view_type', 32)->default('other');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['device_unit_id', 'sort_order']);
        });

        Schema::create('device_unit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type')->index();
            $table->json('changes')->nullable();
            $table->timestamps();
        });

        Schema::table('pending_purchase_sessions', function (Blueprint $table) {
            $table->foreignId('device_unit_id')->nullable()->after('product_variant_id')->constrained()->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('device_unit_id')->nullable()->after('product_variant_id')->constrained()->nullOnDelete();
            $table->json('device_snapshot')->nullable()->after('variant_options');
            $table->unique('device_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique(['device_unit_id']);
            $table->dropForeign(['device_unit_id']);
            $table->dropColumn(['device_unit_id', 'device_snapshot']);
        });
        Schema::table('pending_purchase_sessions', function (Blueprint $table) {
            $table->dropForeign(['device_unit_id']);
            $table->dropColumn('device_unit_id');
        });
        Schema::dropIfExists('device_unit_events');
        Schema::dropIfExists('device_unit_images');
        Schema::dropIfExists('device_units');
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('is_unit_managed'));
    }
};
