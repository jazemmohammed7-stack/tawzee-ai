<div class="max-w-4xl" x-data="{ changed() { return $wire.name.trim() !== $wire.savedName || $wire.allow_negative_stock !== $wire.savedNegativeStock } }">
    <header class="mb-7">
        <p class="mb-2 text-xs font-bold text-teal-800">{{ __('company_settings.section') }}</p>
        <h1 class="text-2xl font-bold sm:text-3xl">{{ __('company_settings.title') }}</h1>
        <p class="mt-3 text-sm leading-7 text-slate-500">{{ __('company_settings.description') }}</p>
    </header>
    <form wire:submit="save" novalidate class="space-y-6">
        @error('settings')<p role="alert" class="ui-alert">{{ $message }}</p>@enderror
        <section class="ui-card p-5 sm:p-7" aria-labelledby="company-information-title">
            <h2 id="company-information-title" class="font-bold">{{ __('company_settings.information') }}</h2>
            <p class="mb-6 mt-2 text-sm leading-7 text-slate-500">{{ __('company_settings.information_hint') }}</p>
            <x-ui.field name="name" :label="__('company_settings.name')" wire:model="name" autocomplete="organization" maxlength="255" required />
        </section>
        <section class="ui-card p-5 sm:p-7" aria-labelledby="company-inventory-title">
            <h2 id="company-inventory-title" class="font-bold">{{ __('company_settings.inventory') }}</h2>
            <p class="mb-6 mt-2 text-sm leading-7 text-slate-500">{{ __('company_settings.inventory_hint') }}</p>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                <label for="negative-stock" class="flex min-h-11 cursor-pointer items-center justify-between gap-4">
                    <span class="min-w-0 text-sm font-bold">{{ __('company_settings.negative_stock') }}</span>
                    <span class="relative inline-flex shrink-0 items-center">
                        <input id="negative-stock" type="checkbox" role="switch" wire:model="allow_negative_stock"
                            class="peer sr-only" aria-describedby="negative-stock-help negative-stock-error"
                            aria-checked="{{ $allow_negative_stock ? 'true' : 'false' }}" x-bind:aria-checked="$wire.allow_negative_stock ? 'true' : 'false'">
                        <span aria-hidden="true" class="h-7 w-12 rounded-full bg-slate-400 transition-colors peer-checked:bg-teal-800 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-teal-600"></span>
                        <span aria-hidden="true" class="pointer-events-none absolute start-1 size-5 rounded-full bg-white shadow-sm transition-transform peer-checked:-translate-x-5"></span>
                    </span>
                </label>
                <p id="negative-stock-help" class="mt-3 max-w-2xl text-sm leading-8 text-slate-600">{{ __('company_settings.negative_hint') }}</p>
                <p class="mt-3 text-xs font-bold text-teal-800" x-text="$wire.allow_negative_stock ? @js(__('company_settings.enabled')) : @js(__('company_settings.disabled'))">{{ $allow_negative_stock ? __('company_settings.enabled') : __('company_settings.disabled') }}</p>
                <div id="negative-stock-error">@error('allow_negative_stock')<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror</div>
            </div>
        </section>
        <div class="ui-card flex flex-wrap items-center justify-between gap-4 p-5">
            <p role="status" class="text-sm text-slate-500" x-text="changed() ? @js(__('company_settings.unsaved')) : @js(__('company_settings.up_to_date'))">{{ __('company_settings.up_to_date') }}</p>
            {{-- Keep the unchanged-state guard separate from Livewire's submit-button restoration. --}}
            <fieldset x-bind:disabled="!changed()">
                <x-ui.button id="save-settings" type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ __('company_settings.save') }}</span>
                    <span wire:loading wire:target="save">{{ __('company_settings.saving') }}</span>
                </x-ui.button>
            </fieldset>
        </div>
    </form>
</div>
