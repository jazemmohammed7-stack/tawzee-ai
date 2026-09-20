@props(['action', 'label'])
<button type="submit" wire:loading.attr="disabled" wire:target="{{ $action }}" class="w-full rounded-lg bg-teal-800 px-5 py-3 font-bold text-white hover:bg-teal-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700 disabled:opacity-60">
    <span wire:loading.remove wire:target="{{ $action }}">{{ $label }}</span>
    <span wire:loading wire:target="{{ $action }}" role="status">{{ __('authentication.loading') }}</span>
</button>
