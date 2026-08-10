<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('display_name')->nullable();
            $table->string('hex_value', 7)->nullable();
            $table->string('swatch_image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_option_id', 'slug']);
            $table->index(['product_option_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_values');
    }
};
