<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /** Remove hidden products left behind by the former soft-delete workflow. */
    public function up(): void
    {
        $deletedProductIds = DB::table('products')->whereNotNull('deleted_at')->pluck('id');

        if ($deletedProductIds->isEmpty()) {
            return;
        }

        $imagePaths = DB::table('product_images')
            ->whereIn('product_id', $deletedProductIds)
            ->pluck('image_path');
        $swatchPaths = DB::table('product_option_values')
            ->join('product_options', 'product_options.id', '=', 'product_option_values.product_option_id')
            ->whereIn('product_options.product_id', $deletedProductIds)
            ->pluck('product_option_values.swatch_image');

        DB::table('products')->whereIn('id', $deletedProductIds)->delete();

        Storage::disk('public')->delete(
            $imagePaths->merge($swatchPaths)->filter()->unique()->values()->all()
        );
    }

    public function down(): void
    {
        // Purged products and their assets cannot be restored.
    }
};
