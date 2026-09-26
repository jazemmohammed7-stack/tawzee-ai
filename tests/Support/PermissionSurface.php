<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Foundation;
use App\Livewire\RegisterCompany;
use App\Modules\Access\Enums\DefaultRole;
use App\Modules\Access\Enums\Permission;
use App\Modules\Company\Livewire\Settings;
use App\Modules\Identity\Livewire\Users;

/** Endpoint inventory only; role grants are always derived from DefaultRole. */
final class PermissionSurface
{
    public const PAGES = [
        'users.index' => ['uri' => 'users', 'permission' => Permission::UsersManage, 'component' => Users::class,
            'ability' => 'can:viewAny,App\\Models\\User'],
        'company.settings' => ['uri' => 'company/settings', 'permission' => Permission::CompanySettings, 'component' => Settings::class,
            'ability' => 'can:viewSettings,App\\Modules\\Company\\Models\\Company'],
    ];

    // Public registration is guest-guarded by the component, not guest middleware.
    public const OTHER_ROUTES = [
        'home' => ['GET|HEAD', '/', 'public'],
        'register' => ['GET|HEAD', 'register', 'component-guest'],
        'login' => ['GET|HEAD', 'login', 'guest'],
        'password.request' => ['GET|HEAD', 'forgot-password', 'guest'],
        'password.reset' => ['GET|HEAD', 'reset-password/{token}', 'guest'],
        'logout' => ['POST', 'logout', 'auth'],
        'setup.pending' => ['GET|HEAD', 'pending-setup', 'tenant'],
    ];

    public const NON_PERMISSION_COMPONENTS = [
        Foundation::class => ['checkConnection', 'render'],
        RegisterCompany::class => ['mount', 'register', 'render'],
        Login::class => ['mount', 'login', 'render'],
        ForgotPassword::class => ['mount', 'sendLink', 'render'],
        ResetPassword::class => ['mount', 'resetPassword', 'render'],
    ];

    public static function allowed(DefaultRole $role, Permission $permission): bool
    {
        return in_array($permission, $role->permissions(), true);
    }

    public static function controlRole(Permission $permission): DefaultRole
    {
        return array_values(array_filter(DefaultRole::cases(), fn ($role) => self::allowed($role, $permission)))[0];
    }

    /** Each key is a tested scenario; save/changeStatus branches are deliberately separate. */
    public static function actions(): array
    {
        $actions = [];
        foreach (['clearFilters', 'openCreate', 'openEdit', 'closeForm', 'cancelStatus',
            'save:create', 'save:update', 'save:role', 'confirmStatus:activate', 'confirmStatus:deactivate',
            'changeStatus:activate', 'changeStatus:deactivate', 'queryStringHandlesPagination', 'getPage',
            'previousPage', 'nextPage', 'gotoPage', 'resetPage', 'setPage', '$refresh:filters'] as $scenario) {
            $actions['users.'.$scenario] = ['users.index', explode(':', $scenario)[0], $scenario];
        }
        $actions['settings.save'] = ['company.settings', 'save', 'settings'];
        $actions['settings.refresh'] = ['company.settings', '$refresh', 'refresh'];

        return $actions;
    }
}
