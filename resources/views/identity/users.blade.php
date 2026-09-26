<div x-data="{ overlayTrigger: null }" x-on:click.capture="if (!$event.target.closest('dialog')) overlayTrigger = $event.target.closest('button')">
    <div class="mb-7 flex flex-wrap items-start justify-between gap-5">
        <div><p class="mb-2 text-xs font-bold text-teal-800">{{ __('users.section') }}</p><h1 class="text-2xl font-bold sm:text-3xl">{{ __('users.title') }}</h1><p class="mt-3 max-w-2xl text-sm leading-7 text-slate-500">{{ __('users.description') }}</p></div>
        @can('create', \App\Models\User::class)
            <x-ui.button id="add-user" wire:click="openCreate" wire:loading.attr="disabled"><span aria-hidden="true" class="text-xl">+</span>{{ __('users.add') }}</x-ui.button>
        @endcan
    </div>
    <div class="mb-7 grid grid-cols-3 gap-2 sm:gap-4">
        @foreach ([['total', $total], ['active_total', $active], ['inactive_total', $total - $active]] as [$label, $count])
        <div class="ui-card px-3 py-4 sm:px-5"><p class="text-[11px] leading-6 text-slate-500 sm:text-xs">{{ __('users.'.$label) }}</p><p class="mt-2 text-2xl font-bold tabular-nums sm:text-3xl">{{ $count }}</p></div>
        @endforeach
    </div>
    <section class="ui-card overflow-hidden" aria-labelledby="users-list-title">
        <div class="border-b border-slate-200 p-5">
            <h2 id="users-list-title" class="mb-5 font-bold">{{ __('users.list') }}</h2>
            <div class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_11rem_10rem_auto]">
                <div><label class="ui-label mb-2" for="user-search">{{ __('users.search') }}</label><input id="user-search" type="search" wire:model.live.debounce.350ms="search" maxlength="255" class="ui-input" placeholder="{{ __('users.search_placeholder') }}"></div>
                <div><label class="ui-label mb-2" for="role-filter">{{ __('users.role') }}</label><select id="role-filter" wire:model.live="roleFilter" class="ui-input"><option value="">{{ __('users.all_roles') }}</option>@foreach ($roles as $role)<option value="{{ $role->id }}">{{ __('users.roles.'.$role->name) }}</option>@endforeach</select></div>
                <div><label class="ui-label mb-2" for="status-filter">{{ __('users.status') }}</label><select id="status-filter" wire:model.live="statusFilter" class="ui-input"><option value="">{{ __('users.all_statuses') }}</option><option value="active">{{ __('users.active') }}</option><option value="inactive">{{ __('users.inactive') }}</option></select></div>
                <x-ui.button variant="quiet" wire:click="clearFilters" wire:loading.attr="disabled">{{ __('users.reset') }}</x-ui.button>
            </div>
            <div class="mt-2 min-h-5 text-xs text-teal-800" role="status"><span wire:loading wire:target="search,roleFilter,statusFilter,clearFilters,nextPage,previousPage">{{ __('users.loading') }}</span></div>
        </div>
        <div wire:loading.class="opacity-50" wire:target="search,roleFilter,statusFilter,nextPage,previousPage">
            @if ($users->isEmpty())
                <div id="users-empty" class="px-6 py-16 text-center">
                    <span class="mx-auto mb-5 grid size-14 place-items-center rounded-2xl bg-teal-50 text-2xl text-teal-800" aria-hidden="true">⌕</span>
                    <h3 class="font-bold">{{ $total === 0 ? __('users.empty_title') : __('users.no_results') }}</h3>
                    <p class="mt-3 text-sm leading-7 text-slate-500">{{ $total === 0 ? __('users.empty_description') : __('users.no_results_description') }}</p>
                    @if ($total > 0)<x-ui.button variant="secondary" class="mt-5" wire:click="clearFilters">{{ __('users.reset') }}</x-ui.button>@endif
                </div>
            @else
                <table class="hidden w-full table-fixed text-start text-sm xl:table" id="users-table">
                    <caption class="sr-only">{{ __('users.list') }}</caption>
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500"><tr><th scope="col" class="w-[25%] px-5 py-4 text-start">{{ __('users.name') }}</th><th scope="col" class="w-[25%] px-3 py-4 text-start">{{ __('users.email') }}</th><th scope="col" class="w-[17%] px-3 py-4 text-start">{{ __('users.role') }}</th><th scope="col" class="w-[13%] px-3 py-4 text-start">{{ __('users.status') }}</th><th scope="col" class="w-[20%] px-3 py-4 text-start">{{ __('users.actions') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($users as $user)
                        <tr wire:key="desktop-user-{{ $user->id }}" class="hover:bg-slate-50/70">
                            <td class="px-5 py-5">@include('identity.user-identity')</td>
                            <td class="break-all px-3 py-5 text-slate-500"><bdi dir="ltr">{{ $user->email }}</bdi></td>
                            <td class="px-3 py-5">@forelse ($user->roles as $role)<x-ui.badge :tone="$role->name === 'owner' ? 'owner' : 'neutral'">{{ __('users.roles.'.$role->name) }}</x-ui.badge>@empty {{ __('users.no_role') }} @endforelse</td>
                            <td class="px-3 py-5"><x-ui.badge :tone="$user->is_active ? 'success' : 'neutral'"><span aria-hidden="true">{{ $user->is_active ? '●' : '○' }}</span>{{ $user->is_active ? __('users.active') : __('users.inactive') }}</x-ui.badge></td>
                            <td class="px-2 py-5">@include('identity.user-actions')</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <ul class="divide-y divide-slate-200 xl:hidden" id="users-cards" aria-label="{{ __('users.list') }}">
                    @foreach ($users as $user)
                    <li wire:key="mobile-user-{{ $user->id }}" class="p-5">
                        <div class="flex items-start justify-between gap-3">@include('identity.user-identity')<x-ui.badge :tone="$user->is_active ? 'success' : 'neutral'">{{ $user->is_active ? __('users.active') : __('users.inactive') }}</x-ui.badge></div>
                        <p class="my-3 break-all text-sm text-slate-500"><bdi dir="ltr">{{ $user->email }}</bdi></p>
                        <div class="flex flex-wrap items-center justify-between gap-2"><div>@forelse ($user->roles as $role)<x-ui.badge :tone="$role->name === 'owner' ? 'owner' : 'neutral'">{{ __('users.roles.'.$role->name) }}</x-ui.badge>@empty {{ __('users.no_role') }} @endforelse</div>@include('identity.user-actions')</div>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>
        {{ $users->links('identity.pagination') }}
    </section>

    <x-ui.modal name="user-form-dialog" open="$wire.formOpen" close="closeForm" :title="$editingId ? __('users.edit_title') : __('users.create_title')">
        <form wire:submit="save" novalidate>
            <div class="space-y-5 px-6 py-5">
                <p class="text-sm text-slate-500">{{ __('users.form_description') }}</p>
                @error('form')<p role="alert" class="ui-alert">{{ $message }}</p>@enderror
                <x-ui.field name="name" :label="__('users.name')" wire:model="name" autocomplete="name" maxlength="255" required />
                <x-ui.field name="email" type="email" :label="__('users.email')" wire:model="email" autocomplete="email" dir="ltr" maxlength="255" required />
                @if (! $editingId)
                    <x-ui.field name="password" type="password" :label="__('users.password')" :hint="__('users.password_hint')" wire:model="password" autocomplete="new-password" dir="ltr" required />
                    <x-ui.field name="password_confirmation" type="password" :label="__('users.password_confirmation')" wire:model="password_confirmation" autocomplete="new-password" dir="ltr" required />
                @endif
                <div class="border-t border-slate-200 pt-5">
                    <h3 class="mb-4 text-sm font-bold">{{ __('users.access') }}</h3>
                    <label class="ui-label mb-2" for="user-role">{{ __('users.role') }}</label>
                    <select id="user-role" wire:model="role_id" class="ui-input" @disabled(! $canChangeRole) aria-describedby="user-role-feedback" aria-invalid="{{ $errors->has('role_id') ? 'true' : 'false' }}" required>
                        <option value="">{{ __('users.select_role') }}</option>
                        @foreach ($roles as $role)<option value="{{ $role->id }}">{{ __('users.roles.'.$role->name) }}</option>@endforeach
                    </select>
                    <div id="user-role-feedback" class="mt-2 text-xs leading-6 text-slate-500"><p>{{ __('users.role_hint') }}</p>@error('role_id')<p role="alert" class="text-red-700">{{ $message }}</p>@enderror</div>
                </div>
                @if (! $canChangeRole)<p class="ui-alert">{{ __('users.protected') }}</p>@endif
                @if ($editingId === $actorId)<p class="ui-alert">{{ __('users.self_warning') }}</p>@endif
            </div>
            <div class="sticky bottom-0 flex flex-wrap justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
                <x-ui.button variant="secondary" wire:click="closeForm" wire:loading.attr="disabled">{{ __('users.cancel') }}</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">{{ $editingId ? __('users.save') : __('users.create') }}</span><span wire:loading wire:target="save">{{ __('users.saving') }}</span></x-ui.button>
            </div>
        </form>
    </x-ui.modal>
    <x-ui.modal name="user-status-dialog" open="$wire.statusId !== null" close="cancelStatus" :title="$nextActive ? __('users.confirm_enable') : __('users.confirm_disable')">
        <div class="space-y-4 p-6"><p class="text-sm leading-8 text-slate-600">{{ __($nextActive ? 'users.enable_description' : 'users.disable_description', ['name' => $statusName]) }}</p>@if ($statusId === $actorId)<p class="ui-alert">{{ __('users.self_warning') }}</p>@endif</div>
        <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
            <x-ui.button variant="secondary" wire:click="cancelStatus" wire:loading.attr="disabled">{{ __('users.cancel') }}</x-ui.button>
            <x-ui.button :variant="$nextActive ? 'primary' : 'danger'" wire:click="changeStatus" wire:loading.attr="disabled" wire:target="changeStatus">{{ $nextActive ? __('users.enable') : __('users.disable') }}</x-ui.button>
        </div>
    </x-ui.modal>
</div>
