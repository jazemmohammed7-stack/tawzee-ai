<section class="mx-auto max-w-xl py-10 sm:py-16" aria-labelledby="forgot-title">
    <h1 id="forgot-title" class="text-2xl font-bold">{{ __('authentication.forgot') }}</h1>
    @if ($sent) <p role="status" class="mt-4 rounded-xl bg-teal-50 p-4 text-teal-900">{{ __('authentication.sent') }}</p> @endif
    <form wire:submit="sendLink" class="mt-8 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
        <x-auth-field name="email" type="email" autocomplete="email" />
        <x-auth-submit action="sendLink" :label="__('authentication.send_link')" />
        <a href="{{ route('login') }}" class="block text-teal-800 underline">{{ __('authentication.login') }}</a>
    </form>
</section>
