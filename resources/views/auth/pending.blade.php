@extends('layouts.app')
@section('title', __('authentication.pending_title'))
@section('content')
<section class="mx-auto max-w-xl py-12" aria-labelledby="pending-title">
    <h1 id="pending-title" class="text-2xl font-bold">{{ __('authentication.pending_title') }}</h1>
    @if ($pending) <p role="status" class="mt-5 rounded-xl bg-teal-50 p-5 leading-8">{{ __('authentication.pending') }}</p> @endif
    <p class="mt-5 leading-8 text-slate-600">{{ __('authentication.temporary') }}</p>
    <form method="POST" action="{{ route('logout') }}" class="mt-8">
        @csrf
        <button class="rounded-lg bg-teal-800 px-5 py-3 font-bold text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700">{{ __('authentication.logout') }}</button>
    </form>
</section>
@endsection
