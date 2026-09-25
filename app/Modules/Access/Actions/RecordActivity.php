<?php

declare(strict_types=1);

namespace App\Modules\Access\Actions;

use App\Models\User;
use App\Modules\Access\Models\ActivityLog;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class RecordActivity
{
    private const REDACTED = '[REDACTED]';

    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly Factory $auth,
    ) {}

    public function record(string $action, Model $subject, array $properties = []): ActivityLog
    {
        $companyId = $this->currentCompany->id();
        $action = trim($action);
        if ($action === '' || mb_strlen($action) > 100 || ! preg_match('/^[a-z0-9][a-z0-9._:-]*$/', $action)) {
            throw new InvalidArgumentException('The activity action is invalid.');
        }

        $this->assertSubjectBelongsToCompany($subject, $companyId);
        $actor = $this->auth->guard()->user();
        if ($actor !== null && (! $actor instanceof User || ! $actor->exists
            || (int) $actor->getRawOriginal('company_id') !== $companyId)) {
            throw new AuthorizationException('The activity actor does not belong to the current company.');
        }

        $log = new ActivityLog;
        $log->forceFill([
            'user_id' => $actor?->getRawOriginal('id'),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'properties' => $this->sanitize($properties),
            'ip_address' => $this->requestIp(),
        ]);
        $log->save();

        return $log;
    }

    private function assertSubjectBelongsToCompany(Model $subject, int $companyId): void
    {
        if (! $subject->exists || $subject->getKey() === null || $subject->isDirty($subject->getKeyName())) {
            throw new AuthorizationException('A persisted subject is required.');
        }
        $subjectCompanyId = $subject instanceof Company
            ? $subject->getRawOriginal($subject->getKeyName())
            : $subject->getRawOriginal('company_id');
        if ($subjectCompanyId === null || (int) $subjectCompanyId !== $companyId
            || ($subject->hasAttribute('company_id') && $subject->isDirty('company_id'))) {
            throw new AuthorizationException('The activity subject does not belong to the current company.');
        }
    }

    private function sanitize(array $properties): array
    {
        $sanitized = [];
        foreach ($properties as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $sanitized[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
            } elseif (is_scalar($value) || $value === null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $key) ?? $key);
        foreach (['password', 'passwd', 'authorization', 'cookie', 'csrf', 'xsrf', 'session',
            'token', 'secret', 'api_key', 'app_key', 'db_password', 'smtp'] as $sensitive) {
            if (str_contains($normalized, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    private function requestIp(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }
        $ip = request()->ip();

        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }
}
