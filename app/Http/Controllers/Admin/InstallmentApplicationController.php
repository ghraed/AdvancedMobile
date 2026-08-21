<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstallmentApplication;
use App\Models\InstallmentApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InstallmentApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = InstallmentApplication::query();
        foreach (['status', 'application_number', 'phone'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, 'like', '%'.$request->$field.'%');
            }
        } if ($request->filled('customer')) {
            $query->where(fn ($q) => $q->where('first_name', 'like', '%'.$request->customer.'%')->orWhere('last_name', 'like', '%'.$request->customer.'%'));
        } if ($request->filled('product')) {
            $query->where('product_name_snapshot', 'like', '%'.$request->product.'%');
        } if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        } if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        } $sort = in_array($request->get('sort'), ['created_at', 'application_number', 'status']) ? $request->get('sort') : 'created_at';
        $apps = $query->withCount('documents')->latest($sort)->paginate(20)->withQueryString();

        return view('admin.installment-applications.index', compact('apps'));
    }

    public function show(InstallmentApplication $installmentApplication)
    {
        $installmentApplication->load(['documents', 'statusHistory.performer', 'reviewer']);

        return view('admin.installment-applications.show', ['application' => $installmentApplication]);
    }

    public function transition(Request $request, InstallmentApplication $installmentApplication)
    {
        $data = $request->validate(['status' => 'required|in:under_review,approved,rejected,cancelled,completed', 'note' => ['nullable', 'string', 'max:5000']]);
        if ($data['status'] === 'rejected') {
            $request->validate(['note' => 'required|string|max:5000']);
        } abort_unless($installmentApplication->canTransitionTo($data['status']), 422, 'Invalid status transition.');
        DB::transaction(function () use ($installmentApplication, $data, $request) {
            $from = $installmentApplication->status;
            $changes = ['status' => $data['status'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()];
            if ($data['status'] === 'approved') {
                $changes['approved_at'] = now();
            } if ($data['status'] === 'rejected') {
                $changes['rejected_at'] = now();
            } if ($data['note'] ?? null) {
                $changes['admin_notes'] = $data['note'];
            } $installmentApplication->update($changes);
            $installmentApplication->statusHistory()->create(['from_status' => $from, 'to_status' => $data['status'], 'note' => $data['note'] ?? null, 'performed_by' => $request->user()->id, 'created_at' => now()]);
        });

        return back()->with('status', 'Application status updated.');
    }

    public function document(InstallmentApplication $installmentApplication, $document)
    {
        $document = $installmentApplication->documents()->findOrFail($document);

        return Storage::disk('local')->download($document->stored_path, $document->original_filename, ['Content-Type' => $document->mime_type]);
    }

    public function previewDocument(InstallmentApplication $installmentApplication, $document)
    {
        $document = $installmentApplication->documents()->findOrFail($document);
        abort_unless($this->isPreviewable($document), 404);
        abort_unless(Storage::disk('local')->exists($document->stored_path), 404);

        return response(Storage::disk('local')->get($document->stored_path), 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="'.$document->original_filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function isPreviewable(InstallmentApplicationDocument $document): bool
    {
        return str_starts_with((string) $document->mime_type, 'image/')
            || $document->mime_type === 'application/pdf';
    }
}
