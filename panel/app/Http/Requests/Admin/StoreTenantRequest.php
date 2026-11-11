<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TenantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTenantRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:tenants,slug'],
            'owner_email' => ['required', 'string', 'email', 'max:255', 'exists:users,email'],
            'status' => ['sometimes', 'required', Rule::enum(TenantStatus::class)],
        ];
    }
}
