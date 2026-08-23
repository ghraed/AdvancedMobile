<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('product_type')->default('other')->after('brand')->index();
            $table->string('accessory_subtype')->nullable()->after('product_type')->index();
        });

        Schema::create('device_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('model_identifier');
            $table->string('model_family')->nullable();
            $table->unsignedSmallInteger('release_year')->nullable();
            $table->string('connector_type')->nullable();
            $table->json('charging_standards')->nullable();
            $table->json('features')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('model_identifier');
            $table->index('model_family');
            $table->index('connector_type');
        });

        Schema::create('accessory_compatible_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accessory_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('compatible_product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['accessory_product_id', 'compatible_product_id'], 'accessory_device_unique');
            $table->index(['compatible_product_id', 'accessory_product_id'], 'device_accessory_index');
        });

        Schema::create('accessory_compatibility_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accessory_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['accessory_product_id', 'product_id'], 'accessory_exclusion_unique');
            $table->index(['product_id', 'accessory_product_id'], 'excluded_device_accessory_index');
        });

        Schema::create('accessory_compatibility_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accessory_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('rule_type');
            $table->string('match_value');
            $table->timestamps();
            $table->unique(['accessory_product_id', 'rule_type', 'match_value'], 'accessory_rule_unique');
            $table->index(['rule_type', 'match_value'], 'compatibility_rule_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accessory_compatibility_rules');
        Schema::dropIfExists('accessory_compatibility_exclusions');
        Schema::dropIfExists('accessory_compatible_products');
        Schema::dropIfExists('device_profiles');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['product_type', 'accessory_subtype']);
        });
    }
};
