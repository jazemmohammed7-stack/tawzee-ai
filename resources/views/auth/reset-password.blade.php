@extends('layouts.app')
@section('title', __('authentication.reset'))
@section('content')
    <livewire:auth.reset-password :token="$token" />
@endsection
