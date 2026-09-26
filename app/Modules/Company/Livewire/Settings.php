<?php

declare(strict_types=1);

namespace App\Modules\Company\Livewire;

use App\Modules\Company\Actions\UpdateCompanySettings;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class Settings extends Component
{
    use AuthorizesRequests;

    public string $name = '';

    // Keep the raw Livewire value until boolean validation; do not coerce a forged string.
    public mixed $allow_negative_stock = false;

    #[Locked]
    public string $contextKey;

    #[Locked]
    public string $savedName = '';

    #[Locked]
    public bool $savedNegativeStock = false;

    public function mount(): void
    {
        $this->authorize('viewSettings', Company::class);
        $this->contextKey = $this->currentContextKey();
        $this->fillFrom(Company::findOrFail(app(CurrentCompany::class)->id()));
    }

    public function hydrate(): void
    {
        $this->guardPage();
    }

    public function save(UpdateCompanySettings $action): void
    {
        $this->guardPage();
        $this->authorize('updateSettings', Company::findOrFail(app(CurrentCompany::class)->id()));
        try {
            $company = $action->handle($this->only(['name', 'allow_negative_stock']));
        } catch (ValidationException|AuthorizationException|ModelNotFoundException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Company settings operation failed.', ['exception_class' => $exception::class]);
            $this->dispatch('toast', type: 'error', message: __('company_settings.failed'));

            return;
        }
        $this->resetErrorBag();
        $this->fillFrom($company);
        Auth::user()->setRelation('company', $company);
        // Sent only after the action's transaction has committed; text is rendered via x-text.
        $this->dispatch('company-name-updated', name: $company->name);
        $this->dispatch('toast', type: 'success', message: __($company->wasChanged()
            ? 'company_settings.saved' : 'company_settings.unchanged'));
    }

    public function render(): View
    {
        $this->guardPage();

        return view('company.settings');
    }

    private function guardPage(): void
    {
        abort_unless(isset($this->contextKey) && hash_equals($this->contextKey, $this->currentContextKey()), 403);
        $this->authorize('viewSettings', Company::class);
    }

    private function currentContextKey(): string
    {
        return hash('sha256', Auth::id().':'.app(CurrentCompany::class)->id());
    }

    private function fillFrom(Company $company): void
    {
        $this->name = $this->savedName = $company->name;
        $this->allow_negative_stock = $this->savedNegativeStock = $company->allow_negative_stock;
    }
}
