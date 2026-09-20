<section class="mx-auto max-w-xl py-10 sm:py-16" aria-labelledby="login-title">
    <h1 id="login-title" class="text-2xl font-bold">{{ __('authentication.login') }}</h1>
    @if (session('status')) <p role="status" class="mt-4 text-teal-900">{{ session('status') }}</p> @endif
    <form wire:submit="login" class="mt-8 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
        <x-auth-field name="email" type="email" autocomplete="username" />
        <x-auth-field name="password" type="password" autocomplete="current-password" />
        <x-auth-submit action="login" :label="__('authentication.login')" />
        <a href="{{ route('password.request') }}" class="block text-teal-800 underline">{{ __('authentication.forgot') }}</a>
        <a href="{{ route('register') }}" class="block text-teal-800 underline">{{ __('registration.title') }}</a>
    </form>
</section>
