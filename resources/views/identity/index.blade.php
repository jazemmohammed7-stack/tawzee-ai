@extends('layouts.workspace')
@section('title', __('users.title'))
@section('content')
    @livewire(\App\Modules\Identity\Livewire\Users::class)
@endsection
