<section class="mx-auto max-w-xl py-10 sm:py-16" aria-labelledby="reset-title">
    <h1 id="reset-title" class="text-2xl font-bold">{{ __('authentication.reset') }}</h1>
    <form wire:submit="resetPassword" class="mt-8 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
        <x-auth-field name="email" type="email" autocomplete="username" />
        <x-auth-field name="password" type="password" autocomplete="new-password" />
        <x-auth-field name="password_confirmation" type="password" autocomplete="new-password" />
        <p class="text-sm leading-6 text-slate-600">{{ __('registration.password_hint') }}</p>
        @error('token') <p role="alert" class="text-red-700">{{ $message }}</p> @enderror
        <x-auth-submit action="resetPassword" :label="__('authentication.reset')" />
        <a href="{{ route('password.request') }}" class="block text-teal-800 underline">{{ __('authentication.forgot') }}</a>
    </form>
</section>
