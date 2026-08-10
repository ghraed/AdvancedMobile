<div class="admin-alert-stack">
    @if (session('status'))
        <div class="admin-alert admin-alert--success" role="status">{{ session('status') }}</div>
    @endif

    @foreach ((array) session('warnings', []) as $warning)
        <div class="admin-alert admin-alert--error" role="alert">{{ $warning }}</div>
    @endforeach

    @if ($errors->has('delete'))
        <div class="admin-alert admin-alert--error" role="alert">{{ $errors->first('delete') }}</div>
    @endif
</div>
