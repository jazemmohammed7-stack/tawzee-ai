<div class="flex flex-wrap items-center gap-1">
    <x-ui.button variant="quiet" wire:click="openEdit({{ $user->id }})" wire:loading.attr="disabled" aria-label="{{ __('users.edit').' '.$user->name }}">{{ __('users.edit') }}</x-ui.button>
    @if (! $user->is_active || $user->id === auth()->id() || ! \App\Modules\Identity\Support\UserProtection::owner($user, $company))
        <x-ui.button variant="quiet" class="{{ $user->is_active ? 'text-red-700' : 'text-teal-800' }}" wire:click="confirmStatus({{ $user->id }}, {{ $user->is_active ? 'false' : 'true' }})" wire:loading.attr="disabled" aria-label="{{ ($user->is_active ? __('users.disable') : __('users.enable')).' '.$user->name }}">{{ $user->is_active ? __('users.disable') : __('users.enable') }}</x-ui.button>
    @endif
</div>
