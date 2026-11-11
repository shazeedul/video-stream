<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var Tenant|string|null $tenant */
        $tenant = $this->route('tenant');
        $tenantId = $tenant instanceof Tenant ? $tenant->id : (is_string($tenant) ? $tenant : null);

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('tenants', 'slug')->ignore($tenantId),
            ],
            'status' => ['sometimes', 'required', Rule::enum(TenantStatus::class)],
        ];
    }
}
