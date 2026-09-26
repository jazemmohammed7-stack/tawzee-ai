<div x-data="{ items: [], add(detail) { const id = Date.now(); this.items.push({id, type: detail.type, message: detail.message}); setTimeout(() => this.items = this.items.filter(item => item.id !== id), 8000); } }"
    x-on:toast.window="add($event.detail)" class="pointer-events-none fixed bottom-5 inset-x-4 z-[100] flex flex-col items-center gap-3 sm:inset-x-auto sm:start-6 sm:max-w-md" aria-live="polite" aria-atomic="true">
    <template x-for="item in items" :key="item.id">
        <div class="pointer-events-auto flex w-full items-start gap-3 rounded-xl border bg-white p-4 shadow-lg" :class="item.type === 'error' ? 'border-red-300' : 'border-teal-300'" :role="item.type === 'error' ? 'alert' : 'status'">
            <span aria-hidden="true" class="grid size-7 shrink-0 place-items-center rounded-full bg-slate-100" x-text="item.type === 'error' ? '!' : '✓'"></span>
            <p class="min-w-0 flex-1 text-sm leading-7" x-text="item.message"></p>
            <button class="ui-button ui-button-quiet size-11 shrink-0 p-0" x-on:click="items = items.filter(other => other.id !== item.id)" aria-label="{{ __('users.dismiss') }}">×</button>
        </div>
    </template>
</div>
