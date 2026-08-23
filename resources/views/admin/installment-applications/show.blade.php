@extends('admin.layouts.app')

@section('heading', 'Application '.$application->application_number)

@section('content')
    <div class="admin-grid admin-grid-2">
        <section class="admin-card">
            @if (! $application->user_id)
                <p class="mb-5 rounded-xl bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-800">Guest application — submitted without an account.</p>
            @endif
            <h3 class="admin-card__title">Customer</h3>
            <p>
                {{ $application->first_name }} {{ $application->last_name }}<br>
                {{ $application->phone }}<br>
                {{ $application->email }}<br>
                {{ $application->address }}
            </p>

            <h3 class="admin-card__title">Device & payment</h3>
            <p>
                {{ $application->product_name_snapshot }}
                {{ $application->storage_snapshot }}
                {{ $application->color_snapshot }}<br>
                {{ $application->product_price }} {{ $application->currency }}
                · {{ $application->monthly_payment }} × {{ $application->installment_months }}
                · Total {{ $application->total_payable }}
            </p>

            <h3 class="admin-card__title">Private documents</h3>
            @forelse ($application->documents as $document)
                @php($isImage = str_starts_with((string) $document->mime_type, 'image/'))
                @php($isPdf = $document->mime_type === 'application/pdf')
                <article class="admin-card admin-card--tight" style="margin-top:16px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px;">
                        <div>
                            <strong>{{ str($document->type)->replace('_', ' ')->headline() }}</strong>
                            <div style="font-size:12px; color:var(--admin-muted);">{{ $document->original_filename }}</div>
                        </div>
                        <a class="admin-link-button" href="{{ route('admin.installment-applications.documents.show', [$application, $document]) }}">Download</a>
                    </div>

                    @if ($isImage)
                        <button
                            type="button"
                            data-image-preview-trigger="{{ $document->id }}"
                            style="display:block; padding:0; border:0; background:transparent; cursor:zoom-in;"
                        >
                            <img
                                src="{{ route('admin.installment-applications.documents.preview', [$application, $document]) }}"
                                alt="{{ $document->type }}"
                                style="display:block; width:auto; max-width:16rem; max-height:12rem; border-radius:16px; border:1px solid var(--admin-border); background:var(--admin-surface-muted); object-fit:cover;"
                            >
                        </button>
                    @elseif ($isPdf)
                        <iframe
                            src="{{ route('admin.installment-applications.documents.preview', [$application, $document]) }}"
                            title="{{ $document->type }}"
                        style="width:100%; height:32rem; border:1px solid var(--admin-border); border-radius:16px; background:var(--admin-surface);"
                        ></iframe>
                    @else
                        <p style="margin:0; color:var(--admin-muted);">Preview is not available for this file type. Use Download.</p>
                    @endif
                </article>
            @empty
                <p>No documents uploaded.</p>
            @endforelse
        </section>

        <section class="admin-card">
            <h3 class="admin-card__title">Status: {{ $application->status }}</h3>

            @if ($application->status === 'approved' && ! $application->installmentAccount)
                @if ($application->user_id)
                    <form method="POST" action="{{ route('admin.installment-applications.activate', $application) }}" class="admin-form-grid" style="margin-bottom:24px">
                        @csrf
                        <label class="admin-field"><span class="admin-label">First payment due date</span><input class="admin-input" type="date" name="first_due_date" value="{{ old('first_due_date', today()->toDateString()) }}" required></label>
                        <button class="admin-button" type="submit">Start installment agreement</button>
                    </form>
                @else
                    <p class="admin-alert admin-alert--error">Assign this guest application to a customer before starting an account.</p>
                @endif
            @elseif ($application->installmentAccount)
                <p><a class="admin-link-button" href="{{ route('admin.installments.show', $application->installmentAccount) }}">View installment account {{ $application->installmentAccount->account_number }}</a></p>
            @endif

            <form method="POST" action="{{ route('admin.installment-applications.transition', $application) }}" class="admin-form-grid">
                @csrf
                @method('PATCH')

                <select class="admin-select" name="status">
                    @foreach (\App\Models\InstallmentApplication::STATUSES as $status)
                        @if ($application->canTransitionTo($status))
                            <option value="{{ $status }}">{{ str_replace('_', ' ', $status) }}</option>
                        @endif
                    @endforeach
                </select>

                <textarea class="admin-textarea" name="note" placeholder="Admin notes (required when rejecting)"></textarea>

                <button class="admin-button" type="submit">Update status</button>
            </form>

            <h3 class="admin-card__title mt-6">History</h3>
            @forelse ($application->statusHistory as $history)
                <p>
                    <strong>{{ $history->to_status }}</strong>
                    - {{ $history->note }}
                    <small>{{ $history->performer?->name }} · {{ $history->created_at }}</small>
                </p>
            @empty
                <p>No status history yet.</p>
            @endforelse
        </section>
    </div>

    @foreach ($application->documents as $document)
        @if (str_starts_with((string) $document->mime_type, 'image/'))
            <div class="admin-modal-backdrop" id="document-preview-{{ $document->id }}">
                <div class="admin-modal" style="width:min(100%, 72rem); padding:20px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px;">
                        <div>
                            <h3 style="margin:0;">{{ str($document->type)->replace('_', ' ')->headline() }}</h3>
                            <p style="margin:6px 0 0;">{{ $document->original_filename }}</p>
                        </div>
                        <button type="button" class="admin-link-button" data-modal-close>Close</button>
                    </div>
                    <img
                        src="{{ route('admin.installment-applications.documents.preview', [$application, $document]) }}"
                        alt="{{ $document->type }}"
                        style="display:block; width:100%; max-height:80vh; object-fit:contain; border-radius:18px; border:1px solid var(--admin-border); background:var(--admin-surface-muted);"
                    >
                </div>
            </div>
        @endif
    @endforeach
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-image-preview-trigger]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const modal = document.getElementById(`document-preview-${trigger.dataset.imagePreviewTrigger}`);

                if (modal) {
                    modal.classList.add('is-open');
                }
            });
        });
    </script>
@endpush
