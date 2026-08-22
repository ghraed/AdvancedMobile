<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInstallmentApplicationRequest;
use App\Models\InstallmentApplication;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Services\InstallmentApplicationCalculator;
use App\Services\CategoryMenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstallmentApplicationController extends Controller
{
    public function landing(CategoryMenuService $categoryMenuService)
    {
        return view('installments.landing', [
            'menuCategories' => $categoryMenuService->visibleRootCategories(),
        ]);
    }

    public function create(Request $request, CategoryMenuService $categoryMenuService)
    {
        $products = Product::query()->publiclyAvailable()->whereHas('variants', fn ($q) => $q->available())->with(['category', 'variants' => fn ($q) => $q->available()->with('optionValues.productOption')])->get();

        $preselected = $request->validate([
            'product_id' => ['nullable', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'installment_months' => ['nullable', 'integer', 'in:3,6,9'],
        ]);

        if (isset($preselected['product_id'], $preselected['variant_id']) && ! $products
            ->firstWhere('id', $preselected['product_id'])?->variants
            ->contains('id', $preselected['variant_id'])) {
            $preselected = [];
        }

        return view('installments.create', [
            'products' => $products,
            'durations' => array_keys(config('installments.durations')),
            'preselected' => $preselected,
            'menuCategories' => $categoryMenuService->visibleRootCategories(),
        ]);
    }

    public function quote(Request $request, InstallmentApplicationCalculator $calculator)
    {
        $data = $request->validate(['product_id' => 'required|integer', 'variant_id' => 'required|integer', 'months' => 'required|integer']);
        $variant = $this->availableVariant($data['product_id'], $data['variant_id']);

        return response()->json($calculator->calculate($variant->price, $data['months']) + ['product' => $variant->product->name, 'sku' => $variant->sku]);
    }

    public function store(StoreInstallmentApplicationRequest $request, InstallmentApplicationCalculator $calculator)
    {
        $data = $request->validated();
        $variant = $this->availableVariant($data['product_id'], $data['variant_id']);
        $calculation = $calculator->calculate($variant->price, (int) $data['installment_months']);
        $variant->loadMissing('optionValues.productOption');
        $options = $variant->optionValues->keyBy(fn ($v) => $v->productOption?->slug);
        $application = DB::transaction(function () use ($request, $data, $variant, $calculation, $options) {
            $app = InstallmentApplication::create(array_merge(collect($data)->only(['first_name', 'last_name', 'phone', 'email', 'address', 'identity_document_type'])->all(), $calculation, ['user_id' => $request->user()?->id, 'application_number' => $this->applicationNumber(), 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'product_name_snapshot' => $variant->product->name, 'product_sku_snapshot' => $variant->sku, 'brand_snapshot' => $variant->product->brand, 'storage_snapshot' => $options->get(ProductOption::STORAGE_SLUG)?->display_name ?? $options->get(ProductOption::STORAGE_SLUG)?->name, 'color_snapshot' => $options->get(ProductOption::COLOR_SLUG)?->display_name ?? $options->get(ProductOption::COLOR_SLUG)?->name, 'installment_months' => $data['installment_months'], 'status' => 'submitted']));
            foreach (['id_front', 'id_back', 'selfie_with_id', 'proof_of_address'] as $type) {
                if (! $request->hasFile($type)) {
                    continue;
                } $file = $request->file($type);
                $path = $file->storeAs('installment-applications/'.$app->id, Str::random(48).'.'.$file->extension(), 'local');
                $app->documents()->create(['type' => $type, 'original_filename' => $file->getClientOriginalName(), 'stored_path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_at' => now()]);
            } $app->statusHistory()->create(['from_status' => null, 'to_status' => 'submitted', 'note' => 'Application submitted', 'performed_by' => $request->user()?->id, 'created_at' => now()]);

            return $app;
        });

        return redirect()->route('installments.success', $application)->with('application_number', $application->application_number);
    }

    public function success(InstallmentApplication $application)
    {
        if ($application->user_id && auth()->check()) {
            $this->authorize('view', $application);
        } abort_unless(session('application_number') === $application->application_number || (auth()->check() && auth()->id() === $application->user_id), 403);

        return view('installments.success', compact('application'));
    }

    public function index()
    {
        $applications = auth()->user()->installmentApplications()->latest()->paginate(12);

        return view('installments.index', compact('applications'));
    }

    public function show(InstallmentApplication $application)
    {
        $this->authorize('view', $application);

        return view('installments.show', compact('application'));
    }

    public function document(InstallmentApplication $application, $document)
    {
        $this->authorize('viewDocument', $application);
        $document = $application->documents()->findOrFail($document);

        return Storage::disk('local')->download($document->stored_path, $document->original_filename, ['Content-Type' => $document->mime_type]);
    }

    private function availableVariant(int $productId, int $variantId): ProductVariant
    {
        return ProductVariant::query()->available()->whereKey($variantId)->where('product_id', $productId)->whereHas('product', fn ($q) => $q->publiclyAvailable())->with('product')->firstOrFail();
    }

    private function applicationNumber(): string
    {
        do {
            $number = 'INS-'.now()->format('Ymd').'-'.str_pad((string) (InstallmentApplication::whereDate('created_at', today())->lockForUpdate()->count() + 1), 6, '0', STR_PAD_LEFT);
        } while (InstallmentApplication::where('application_number',$number)->exists());

        return $number;
    }
}
