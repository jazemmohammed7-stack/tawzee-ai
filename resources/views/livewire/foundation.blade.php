<section class="rounded-3xl bg-teal-950 p-7 text-white shadow-xl shadow-teal-900/10 sm:p-10" aria-labelledby="interaction-title">
    <div aria-hidden="true" class="mb-8 flex size-14 items-center justify-center rounded-2xl border border-teal-700 bg-teal-900 text-2xl">↗</div>
    <h2 id="interaction-title" class="text-2xl font-bold">{{ __('foundation.preview') }}</h2>
    <p class="mt-3 text-sm leading-7 text-teal-100">{{ __('foundation.preview_description') }}</p>
    <button type="button" wire:click="checkConnection" wire:loading.attr="disabled" wire:target="checkConnection"
        class="mt-8 min-h-12 w-full rounded-xl bg-white px-5 py-3 text-sm font-bold text-teal-950 transition hover:bg-teal-50 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white disabled:cursor-wait disabled:opacity-70">
        <span wire:loading.remove wire:target="checkConnection">{{ __('foundation.button') }}</span>
        <span wire:loading wire:target="checkConnection">{{ __('foundation.loading') }}</span>
    </button>
    <p role="status" aria-live="polite" aria-atomic="true" class="mt-5 min-h-12 text-center text-sm leading-6 text-teal-100">{{ __('foundation.'.($checked ? 'success' : 'ready')) }}</p>
    <noscript><p class="mt-3 text-sm">{{ __('foundation.no_script') }}</p></noscript>
</section>
