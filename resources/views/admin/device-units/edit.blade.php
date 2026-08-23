@extends('admin.layouts.app')
@section('title', 'Edit Device Unit')
@section('heading', 'Edit '.$deviceUnit->variant->product->name)
@section('page_description', 'Identifier '.$deviceUnit->masked_imei.' · changes are recorded in the device audit trail.')
@section('content')<form method="POST" enctype="multipart/form-data" action="{{ route('admin.device-units.update', $deviceUnit) }}">@csrf @method('PUT') @include('admin.device-units._form')</form>@endsection
