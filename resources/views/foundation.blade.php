@extends('layouts.app')

@section('content')
    <section class="grid items-center gap-10 py-14 sm:py-20 lg:grid-cols-2 lg:gap-16">
        <div>
            <p class="mb-5 text-sm font-bold text-teal-800">{{ __('foundation.eyebrow') }}</p>
            <h1 class="max-w-xl text-4xl leading-relaxed font-bold tracking-tight sm:text-5xl sm:leading-relaxed">{{ __('foundation.heading') }}</h1>
            <p class="mt-6 max-w-lg text-base leading-8 text-slate-600">{{ __('foundation.description') }}</p>
            <p class="mt-5 border-s-2 border-teal-600 ps-4 text-sm leading-7 text-slate-600">{{ __('foundation.note') }}</p>
        </div>
        <livewire:foundation />
    </section>
    <section class="grid gap-5 pb-14 sm:grid-cols-3">
        @foreach (['arabic', 'responsive', 'simple'] as $feature)
            <article class="rounded-2xl border border-slate-200 bg-white p-6">
                <span aria-hidden="true" class="mb-5 block h-1 w-8 rounded-full bg-teal-700"></span>
                <h2 class="text-base font-bold">{{ __('foundation.'.$feature.'_title') }}</h2>
                <p class="mt-3 text-sm leading-7 text-slate-600">{{ __('foundation.'.$feature.'_description') }}</p>
            </article>
        @endforeach
    </section>
@endsection
