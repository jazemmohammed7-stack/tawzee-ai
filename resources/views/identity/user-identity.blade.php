<div class="flex min-w-0 items-center gap-3">
    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-slate-100 font-bold text-slate-600" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
    <div class="min-w-0">
        <p class="break-words font-bold text-slate-800">{{ $user->name }}</p>
        <div class="mt-1 flex flex-wrap items-center gap-1.5">
            @if ($user->id === auth()->id())<span class="text-xs text-slate-500">{{ __('users.you') }}</span>@endif
            @if ((int) $company->founder_user_id === (int) $user->id)<x-ui.badge tone="owner">{{ __('users.founder') }}</x-ui.badge>@endif
        </div>
    </div>
</div>
