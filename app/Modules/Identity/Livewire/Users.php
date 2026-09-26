<?php

declare(strict_types=1);

namespace App\Modules\Identity\Livewire;

use App\Models\User;
use App\Modules\Access\Enums\DefaultRole;
use App\Modules\Access\Models\Role;
use App\Modules\Company\Models\Company;
use App\Modules\Identity\Actions\ChangeUserStatus;
use App\Modules\Identity\Actions\CreateUser;
use App\Modules\Identity\Actions\UpdateUser;
use App\Modules\Identity\Support\CompanyUsers;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class Users extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $role_id = '';

    #[Locked]
    public int $actorId;

    #[Locked]
    public string $contextKey;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public bool $formOpen = false;

    #[Locked]
    public ?int $statusId = null;

    #[Locked]
    public bool $nextActive = false;

    #[Locked]
    public string $statusName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
        $this->actorId = (int) Auth::id();
        $this->contextKey = $this->currentContextKey();
    }

    public function hydrate(): void
    {
        $this->guardPage();
    }

    public function updated(string $property): void
    {
        $this->guardPage();
        if (in_array($property, ['search', 'roleFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->guardPage();
        $this->reset('search', 'roleFilter', 'statusFilter');
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->guardPage();
        $this->authorize('create', User::class);
        $this->clearForm();
        $this->formOpen = true;
    }

    public function openEdit(int $id): void
    {
        $this->guardPage();
        $user = CompanyUsers::query()->with('roles')->findOrFail($id);
        $this->authorize('update', $user);
        $this->clearForm();
        $this->editingId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role_id = (string) ($user->roles->first()?->id ?? '');
        $this->formOpen = true;
    }

    public function closeForm(): void
    {
        $this->guardPage();
        $this->clearForm();
    }

    public function save(): void
    {
        $this->guardPage();
        if ($this->editingId === null) {
            $this->authorize('create', User::class);
        } else {
            $this->authorize('update', CompanyUsers::query()->findOrFail($this->editingId));
        }
        $this->perform(function (): void {
            $input = $this->only(['name', 'email', 'role_id']);
            if ($this->editingId === null) {
                app(CreateUser::class)->handle($input + $this->only(['password', 'password_confirmation']));
            } else {
                app(UpdateUser::class)->handle($this->editingId, $input);
            }
            $this->clearForm();
            $this->resetPage();
            $this->dispatch('toast', type: 'success', message: __('users.saved'));
            if (! Gate::forUser(CompanyUsers::actor())->allows('viewAny', User::class)) {
                $this->redirectRoute('setup.pending');
            }
        });
    }

    public function confirmStatus(int $id, bool $active): void
    {
        $this->guardPage();
        $target = CompanyUsers::query()->findOrFail($id);
        $this->authorize('changeStatus', [$target, $active]);
        $this->statusId = $id;
        $this->nextActive = $active;
        $this->statusName = $target->name;
    }

    public function cancelStatus(): void
    {
        $this->guardPage();
        $this->reset('statusId', 'statusName', 'nextActive');
    }

    public function changeStatus(): void
    {
        $this->guardPage();
        abort_if($this->statusId === null, 404);
        $target = CompanyUsers::query()->findOrFail($this->statusId);
        $this->authorize('changeStatus', [$target, $this->nextActive]);
        $this->perform(function (): void {
            $user = app(ChangeUserStatus::class)->handle($this->statusId, $this->nextActive);
            $this->reset('statusId', 'statusName', 'nextActive');
            $this->dispatch('toast', type: 'success', message: __('users.status_saved'));
            if ((int) $user->id === $this->actorId && ! $user->is_active) {
                $this->redirectRoute('login');
            }
        });
    }

    public function render(): View
    {
        $this->guardPage();
        // Filter validation must not clear the form's Livewire error bag during rendering.
        Validator::make($this->only(['search', 'roleFilter', 'statusFilter']), [
            'search' => ['string', 'max:255'],
            'roleFilter' => ['nullable', 'integer'],
            'statusFilter' => ['in:,active,inactive'],
        ], __('users.validation'), __('users.attributes'))->validate();
        $query = CompanyUsers::query();
        $total = (clone $query)->count();
        $active = (clone $query)->where('is_active', true)->count();
        if ($this->search !== '') {
            // Bound LIKE pattern; escape wildcard characters so search is literal.
            $term = '%'.addcslashes(trim($this->search), '\\%_').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }
        if ($this->roleFilter !== '') {
            $query->whereHas('roles', fn ($q) => $q->where('roles.id', $this->roleFilter));
        }
        if ($this->statusFilter !== '') {
            $query->where('is_active', $this->statusFilter === 'active');
        }

        return view('identity.users', [
            'users' => $query->with('roles')->orderBy('name')->orderBy('id')->paginate(10),
            'roles' => Role::where('guard_name', 'web')->whereIn('name', DefaultRole::values())->orderBy('id')->get(),
            'company' => Company::findOrFail(app(CurrentCompany::class)->id()),
            'total' => $total, 'active' => $active,
            'canChangeRole' => $this->editingId === null || Gate::allows('changeRole', CompanyUsers::query()->findOrFail($this->editingId)),
        ]);
    }

    private function guardPage(): void
    {
        abort_unless(isset($this->actorId) && $this->actorId === (int) Auth::id(), 403);
        abort_unless(isset($this->contextKey) && hash_equals($this->contextKey, $this->currentContextKey()), 403);
        $this->authorize('viewAny', User::class);
    }

    private function clearForm(): void
    {
        $this->reset('editingId', 'formOpen', 'name', 'email', 'role_id', 'password', 'password_confirmation');
        $this->resetErrorBag();
    }

    private function currentContextKey(): string
    {
        return hash('sha256', Auth::id().':'.app(CurrentCompany::class)->id());
    }

    private function perform(Closure $callback): void
    {
        try {
            $callback();
        } catch (ValidationException|AuthorizationException|ModelNotFoundException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('User management operation failed.', ['exception_class' => $exception::class]);
            $this->dispatch('toast', type: 'error', message: __('users.failed'));
        }
    }
}
