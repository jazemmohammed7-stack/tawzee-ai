@props(['name', 'label', 'type' => 'text', 'hint' => null])
<div class="space-y-2">
    <label for="user-{{ $name }}" class="ui-label">{{ $label }}</label>
    <input id="user-{{ $name }}" type="{{ $type }}" {{ $attributes->class('ui-input') }}
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" aria-describedby="user-{{ $name }}-feedback">
    <div id="user-{{ $name }}-feedback" class="text-xs leading-6">
        @if ($hint)<p class="text-slate-500">{{ $hint }}</p>@endif
        @error($name)<p class="text-red-700" role="alert">{{ $message }}</p>@enderror
    </div>
</div>
