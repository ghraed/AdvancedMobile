@extends('admin.layouts.app')
@section('title', 'Device Intake')
@section('heading', 'Intake a physical device')
@section('page_description', 'Select an existing catalog variant, inspect the exact phone, add real photos, then publish it.')
@section('content')<form method="POST" enctype="multipart/form-data" action="{{ route('admin.device-units.store') }}">@csrf @include('admin.device-units._form')</form>@endsection
