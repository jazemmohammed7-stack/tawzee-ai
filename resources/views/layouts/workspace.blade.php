<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — {{ __('users.brand') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="workspace min-h-screen bg-slate-50 font-sans text-slate-900 antialiased"
    x-data="{ companyName: @js(auth()->user()->company->name) }" x-on:company-name-updated.window="companyName = $event.detail.name">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[100] focus:bg-white focus:p-4">{{ __('users.skip') }}</a>
    <aside class="fixed inset-y-0 start-0 z-30 hidden w-64 flex-col bg-slate-950 px-5 py-8 text-white lg:flex" aria-label="{{ __('users.navigation') }}">
        <a href="{{ route('setup.pending') }}" class="flex items-center gap-3 rounded-lg px-2">
            <span class="grid size-11 place-items-center rounded-xl bg-teal-400 text-2xl font-bold text-slate-950" aria-hidden="true">{{ mb_substr(__('users.brand'), 0, 1) }}</span>
            <span><span class="block text-2xl font-bold tracking-tight">{{ __('users.brand') }}</span><span class="text-xs text-slate-400">{{ __('users.brand_caption') }}</span></span>
        </a>
        <p class="mb-3 mt-12 px-4 text-xs text-slate-400">{{ __('users.workspace') }}</p>
        <nav><x-workspace.navigation /></nav>
        <div class="mt-auto rounded-xl border border-white/10 bg-white/5 p-4">
            <p class="text-xs text-slate-400">{{ __('users.company') }}</p>
            <p data-company-name class="mt-2 break-words text-sm font-bold" x-text="companyName">{{ auth()->user()->company->name }}</p>
            <p class="mt-3 text-xs leading-6 text-slate-400">{{ __('users.team_note') }}</p>
        </div>
    </aside>
    <div class="min-w-0 lg:ps-64">
        <header class="flex min-h-20 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 sm:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <div x-data="{ opener: null }" class="lg:hidden">
                    <x-ui.button id="mobile-menu-button" variant="secondary" x-on:click="opener = $el; $refs.drawer.showModal()" aria-haspopup="dialog" aria-controls="mobile-navigation" aria-label="{{ __('users.open_menu') }}" class="size-11 p-0">
                        <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </x-ui.button>
                    <dialog id="mobile-navigation" x-ref="drawer" x-on:close="opener?.focus()" class="mobile-drawer" aria-label="{{ __('users.navigation') }}">
                        <div class="mb-10 flex items-center justify-between gap-3"><span class="text-2xl font-bold">{{ __('users.brand') }}</span><button class="ui-button size-11 p-0 text-white" x-on:click="$refs.drawer.close()" aria-label="{{ __('users.close') }}">×</button></div>
                        <nav><x-workspace.navigation /></nav>
                        <p data-company-name class="mt-8 break-words text-sm text-slate-300" x-text="companyName">{{ auth()->user()->company->name }}</p>
                    </dialog>
                </div>
                <div class="min-w-0"><p class="text-[11px] text-slate-500">{{ __('users.company') }}</p><p data-company-name class="max-w-[40vw] truncate text-sm font-bold sm:max-w-lg" x-text="companyName">{{ auth()->user()->company->name }}</p></div>
            </div>
            <div class="flex min-w-0 items-center gap-3 sm:gap-5">
                <div class="hidden min-w-0 text-end sm:block"><p class="max-w-40 truncate text-sm font-bold">{{ auth()->user()->name }}</p><p dir="ltr" class="max-w-48 truncate text-xs text-slate-500">{{ auth()->user()->email }}</p></div>
                <span title="{{ auth()->user()->name }}" class="grid size-10 shrink-0 place-items-center rounded-full bg-teal-50 text-sm font-bold text-teal-800">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                <form method="POST" action="{{ route('logout') }}">@csrf<x-ui.button type="submit" variant="quiet" class="px-2 text-xs">{{ __('users.logout') }}</x-ui.button></form>
            </div>
        </header>
        <main id="main" tabindex="-1" class="mx-auto max-w-[1440px] px-4 py-7 sm:px-8 sm:py-9 lg:px-10">@yield('content')</main>
    </div>
    <x-ui.toasts />
    @livewireScripts
</body>
</html>
