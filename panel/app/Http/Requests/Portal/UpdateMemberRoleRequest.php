<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Domain\Tenants\Contracts\TenantContextServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMemberRoleRequest extends FormRequest
{
    /**
     * Resolve the current tenant for this request.
     */
    public function currentTenant(): ?Tenant
    {
        return app(TenantContextServiceInterface::class)->getActiveTenant($this->user());
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

        return $this->user()?->can('manageMembers', $currentTenant) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(TenantRole::class)],
        ];
    }
}
