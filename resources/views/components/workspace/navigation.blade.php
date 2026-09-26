@can('viewAny', \App\Models\User::class)
<a href="{{ route('users.index') }}" @if(request()->routeIs('users.index')) aria-current="page" @endif
    @class(['flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold', 'bg-teal-400/15 text-teal-100 ring-1 ring-inset ring-teal-400/20' => request()->routeIs('users.index'), 'text-slate-300 hover:bg-white/5 hover:text-white' => !request()->routeIs('users.index')])>
    <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    {{ __('users.title') }}
</a>
@endcan
@can('viewSettings', \App\Modules\Company\Models\Company::class)
<a href="{{ route('company.settings') }}" @if(request()->routeIs('company.settings')) aria-current="page" @endif
    @class(['mt-2 flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold', 'bg-teal-400/15 text-teal-100 ring-1 ring-inset ring-teal-400/20' => request()->routeIs('company.settings'), 'text-slate-300 hover:bg-white/5 hover:text-white' => !request()->routeIs('company.settings')])>
    <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3" fill="currentColor"/><circle cx="15" cy="17" r="3" fill="currentColor"/></svg>
    {{ __('company_settings.title') }}
</a>
@endcan
<a href="{{ route('setup.pending') }}" class="mt-2 flex min-h-12 items-center rounded-xl px-4 text-sm text-slate-300 hover:bg-white/5 hover:text-white">{{ __('users.setup') }}</a>
