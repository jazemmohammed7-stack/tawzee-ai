<section class="mx-auto max-w-xl py-10 sm:py-16" aria-labelledby="registration-title">
    <h1 id="registration-title" class="text-2xl font-bold sm:text-3xl">{{ __('registration.title') }}</h1>
    <p class="mt-3 leading-7 text-slate-600">{{ __('registration.intro') }}</p>
    @if ($registered)
        <p role="status" class="mt-8 rounded-xl border border-teal-200 bg-teal-50 p-6 leading-8 text-teal-900">{{ __('registration.success') }}</p>
        <a href="{{ route('login') }}" class="mt-5 inline-block text-teal-800 underline">{{ __('authentication.login') }}</a>
    @else
        <form wire:submit="register" class="mt-8 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            @error('registration') <p role="alert" class="text-red-700">{{ $message }}</p> @enderror
            @foreach (['company_name', 'name', 'email', 'password', 'password_confirmation'] as $field)
                @php
                    $type = str_starts_with($field, 'password') ? 'password' : ($field === 'email' ? 'email' : 'text');
                    $autocomplete = match ($field) { 'company_name' => 'organization', 'name' => 'name', 'email' => 'email', default => 'new-password' };
                @endphp
                <div wire:key="registration-{{ $field }}">
                    <label for="{{ $field }}" class="mb-2 block font-semibold">{{ __('registration.attributes.'.$field) }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" wire:model="{{ $field }}"
                        autocomplete="{{ $autocomplete }}" required maxlength="{{ $type === 'password' ? 72 : 255 }}"
                        @if ($type === 'password') minlength="12" @endif
                        @if ($field === 'email') dir="ltr" @endif
                        aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}"
                        aria-describedby="{{ $field }}-error{{ $field === 'password' ? ' password-hint' : '' }}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-3 focus:border-teal-700 focus:outline-2 focus:outline-teal-700">
                    <p id="{{ $field }}-error" role="alert" class="mt-1 text-sm text-red-700">@error($field){{ $message }}@enderror</p>
                </div>
            @endforeach
            <p id="password-hint" class="text-sm leading-6 text-slate-600">{{ __('registration.password_hint') }}</p>
            <button type="submit" wire:loading.attr="disabled" wire:target="register" class="w-full rounded-lg bg-teal-800 px-5 py-3 font-bold text-white hover:bg-teal-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="register">{{ __('registration.submit') }}</span>
                <span wire:loading wire:target="register" role="status">{{ __('registration.loading') }}</span>
            </button>
        </form>
    @endif
</section>
