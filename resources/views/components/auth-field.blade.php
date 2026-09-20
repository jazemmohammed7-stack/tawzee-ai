@props(['name', 'type' => 'text', 'autocomplete' => 'off'])
<div>
    <label for="{{ $name }}" class="mb-2 block font-semibold">{{ __('authentication.attributes.'.$name) }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" wire:model="{{ $name }}" required
        autocomplete="{{ $autocomplete }}" maxlength="{{ $type === 'password' ? 4096 : 255 }}"
        @if ($name === 'email') dir="ltr" @endif
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" aria-describedby="{{ $name }}-error"
        class="w-full rounded-lg border border-slate-300 px-3 py-3 focus:outline-2 focus:outline-teal-700">
    <p id="{{ $name }}-error" role="alert" class="mt-1 text-sm text-red-700">@error($name){{ $message }}@enderror</p>
</div>
