@extends('layouts.workspace')
@section('title', __('company_settings.title'))
@section('content')
    @livewire(\App\Modules\Company\Livewire\Settings::class)
@endsection
