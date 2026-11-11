<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContextService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateOrganizationRequest extends FormRequest
{
    /**
     * Resolve the current tenant for this request.
     */
    public function currentTenant(): ?Tenant
    {
        if (app()->bound('current_tenant')) {
            $tenant = app('current_tenant');
            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        $user = $this->user();

        return $user !== null ? app(TenantContextService::class)->getCurrentTenant($user) : null;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $currentTenant = $this->currentTenant();

        if ($currentTenant === null) {
            return false;
        }

        return $this->user()?->can('update', $currentTenant) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currentTenant = $this->currentTenant();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('tenants', 'slug')->ignore($currentTenant?->id),
            ],
        ];
    }
}
