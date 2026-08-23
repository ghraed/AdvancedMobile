<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeviceCheckStatus;
use App\Enums\DeviceConditionGrade;
use App\Enums\DeviceConditionType;
use App\Enums\DeviceUnitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceUnitRequest;
use App\Models\DeviceUnit;
use App\Models\ProductVariant;
use App\Services\DeviceInventoryService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeviceUnitController extends Controller
{
    public function __construct(protected DeviceInventoryService $inventory) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));
        $query = DeviceUnit::query()->with(['variant.product', 'variant.optionValues.productOption', 'images']);
        if ($search !== '') {
            $imeiHash = DeviceUnit::imeiHash($search);
            $serialHash = DeviceUnit::imeiHash($search);
            $query->where(fn (Builder $q) => $q->where('imei_hash', $imeiHash)->orWhere('serial_number_hash', $serialHash)
                ->orWhereHas('variant', fn (Builder $v) => $v->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', "%{$search}%")->orWhere('brand', 'like', "%{$search}%"))));
        }
        foreach (['status', 'condition_type', 'condition_grade'] as $filter) {
            if ($request->filled($filter)) $query->where($filter, $request->input($filter));
        }
        if ($request->filled('battery_min')) $query->where('battery_health_percent', '>=', (int) $request->input('battery_min'));
        if ($request->input('warranty') === 'yes') $query->where(fn ($q) => $q->where('warranty_days', '>', 0)->orWhere('warranty_until', '>=', today()));
        if ($request->input('warranty') === 'no') $query->whereNull('warranty_days')->whereNull('warranty_until');

        return view('admin.device-units.index', [
            'units' => $query->latest()->paginate(20)->withQueryString(),
            'conditions' => DeviceConditionType::cases(), 'grades' => DeviceConditionGrade::cases(), 'statuses' => DeviceUnitStatus::cases(),
        ]);
    }

    public function create(): View { return view('admin.device-units.create', $this->formData(new DeviceUnit)); }

    public function store(DeviceUnitRequest $request): RedirectResponse
    {
        try {
            $unit = $this->inventory->save(new DeviceUnit, $request->validated(), $request->user());
            return redirect()->route('admin.device-units.edit', $unit)->with('status', 'Device unit created and inventory synchronized.');
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['imei' => $exception->getMessage()]);
        }
    }

    public function show(DeviceUnit $deviceUnit): View
    {
        $deviceUnit->load(['variant.product', 'variant.optionValues.productOption', 'images', 'events.actor', 'orderItem.order']);
        return view('admin.device-units.show', $this->formData($deviceUnit));
    }

    public function edit(DeviceUnit $deviceUnit): View
    {
        $deviceUnit->load(['variant.product', 'variant.optionValues.productOption', 'images']);
        return view('admin.device-units.edit', $this->formData($deviceUnit));
    }

    public function update(DeviceUnitRequest $request, DeviceUnit $deviceUnit): RedirectResponse
    {
        try {
            $this->inventory->save($deviceUnit, $request->validated(), $request->user());
            return back()->with('status', 'Device unit updated and inventory synchronized.');
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['imei' => $exception->getMessage()]);
        }
    }

    public function retire(Request $request, DeviceUnit $deviceUnit): RedirectResponse
    {
        abort_if($deviceUnit->status === DeviceUnitStatus::Sold, 422, 'Sold devices cannot be retired.');
        $payload = $deviceUnit->toArray();
        $payload['imei'] = $deviceUnit->imei;
        $payload['serial_number'] = $deviceUnit->serial_number;
        $payload['status'] = DeviceUnitStatus::Retired->value;
        $this->inventory->save($deviceUnit, $payload, $request->user());
        return back()->with('status', 'Device retired.');
    }

    protected function formData(DeviceUnit $unit): array
    {
        return [
            'deviceUnit' => $unit,
            'variants' => ProductVariant::query()->with(['product:id,name,brand', 'optionValues.productOption'])->where('is_active', true)->orderBy('sku')->get(),
            'conditions' => collect(DeviceConditionType::cases())->reject(fn ($type) => $type === DeviceConditionType::New),
            'grades' => DeviceConditionGrade::cases(), 'statuses' => DeviceUnitStatus::cases(),
            'checkStatuses' => DeviceCheckStatus::cases(), 'checklistKeys' => DeviceUnit::CHECKLIST_KEYS,
            'accessoryKeys' => DeviceUnit::ACCESSORY_KEYS,
        ];
    }
}
