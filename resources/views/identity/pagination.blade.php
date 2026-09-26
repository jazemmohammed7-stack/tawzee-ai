@if ($paginator->hasPages())
<nav role="navigation" aria-label="{{ __('users.pagination') }}" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
    <p class="text-xs text-slate-500">{{ __('users.range', ['from' => $paginator->firstItem() ?? 0, 'to' => $paginator->lastItem() ?? 0, 'total' => $paginator->total()]) }}</p>
    <div class="flex items-center gap-2">
        <x-ui.button variant="secondary" wire:click="previousPage" wire:loading.attr="disabled" :disabled="$paginator->onFirstPage()">{{ __('users.previous') }}</x-ui.button>
        <span class="px-2 text-xs" aria-current="page">{{ __('users.page', ['page' => $paginator->currentPage()]) }}</span>
        <x-ui.button variant="secondary" wire:click="nextPage" wire:loading.attr="disabled" :disabled="! $paginator->hasMorePages()">{{ __('users.next') }}</x-ui.button>
    </div>
</nav>
@endif
