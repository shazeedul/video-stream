<?php

declare(strict_types=1);

namespace App\Http\Requests\Role;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoleRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9._]+$/',
                Rule::unique('roles', 'name')->ignore($this->role->id ?? null),
            ],
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'guard_name' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The role name is required.',
            'name.unique' => 'A role with this name already exists.',
            'name.regex' => 'The role name may only contain lowercase letters, numbers, dots (.), and underscores (_).',
            'display_name.required' => 'The display name is required.',
            'display_name.max' => 'The display name may not be greater than 255 characters.',
            'description.max' => 'The description may not be greater than 500 characters.',
            'permissions.*.exists' => 'One or more selected permissions are invalid.',
            'permissions.*.integer' => 'Permission IDs must be valid integers.',
        ];
    }
}
