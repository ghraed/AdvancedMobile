<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $table->foreignId('product_option_value_id')->nullable()->after('product_id')->constrained('product_option_values')->nullOnDelete();
            $table->index(['product_id', 'product_option_value_id', 'sort_order'], 'product_images_color_gallery_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $table->dropIndex('product_images_color_gallery_index');
            $table->dropConstrainedForeignId('product_option_value_id');
        });
    }
};
