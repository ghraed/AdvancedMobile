@if ($errors->any())
    <div class="admin-alert-stack" role="alert">
        <div class="admin-alert admin-alert--error">
            <strong>There were validation errors.</strong>
            <ul style="margin:10px 0 0; padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
