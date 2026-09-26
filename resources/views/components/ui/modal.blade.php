@props(['name', 'open', 'close', 'title'])
<dialog id="{{ $name }}" wire:ignore.self class="ui-dialog" aria-labelledby="{{ $name }}-title"
    x-data="{ previous: null, sync(open) { if (open && !this.$el.open) { this.previous = this.overlayTrigger || document.activeElement; this.$el.showModal(); } else if (!open && this.$el.open) { this.$el.close(); this.$nextTick(() => requestAnimationFrame(() => this.previous?.focus())); } } }"
    x-effect="sync({{ $open }})" x-on:cancel.prevent="$wire.{{ $close }}()">
    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
        <h2 id="{{ $name }}-title" class="text-xl font-bold">{{ $title }}</h2>
        <x-ui.button variant="quiet" wire:click="{{ $close }}" wire:loading.attr="disabled" aria-label="{{ __('users.close') }}" class="size-11 shrink-0 p-0">×</x-ui.button>
    </div>
    {{ $slot }}
</dialog>
