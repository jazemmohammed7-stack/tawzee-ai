<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="referrer" content="no-referrer">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ __('foundation.description') }}">
    <title>@yield('title', __('foundation.title'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-stone-50 font-sans text-slate-900 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4">{{ __('foundation.skip') }}</a>
    <div class="mx-auto max-w-6xl px-5 sm:px-8">
        <header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 py-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700">
                <span aria-hidden="true" class="grid size-11 place-items-center rounded-2xl bg-teal-800 text-2xl font-bold text-white">ت</span>
                <span><span class="block text-xl font-bold">{{ __('foundation.brand') }}</span><span class="text-xs text-slate-600">{{ __('foundation.tagline') }}</span></span>
            </a>
            <span class="rounded-full border border-teal-200 bg-teal-50 px-4 py-2 text-xs font-semibold text-teal-900">{{ __('foundation.stage') }}</span>
        </header>
        <main id="main" tabindex="-1">@yield('content')</main>
        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 py-6 text-xs text-slate-600">
            <p>{{ __('foundation.footer') }}</p>
            <p>{{ __('foundation.time') }} <time dir="ltr" datetime="{{ now()->toIso8601String() }}">{{ now()->timezone(config('tawzee.display_timezone'))->format('H:i') }}</time></p>
        </footer>
    </div>
    @livewireScripts
</body>
</html>
