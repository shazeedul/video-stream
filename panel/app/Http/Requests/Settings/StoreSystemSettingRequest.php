<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\SystemSettingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreSystemSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('system-settings.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', 'unique:system_settings,key'],
            'value' => ['nullable', 'string'],
            'type' => ['required', new Enum(SystemSettingType::class)],
            'rules' => ['nullable', 'json'],
            'remarks' => ['nullable', 'string'],
            'is_encrypted' => ['boolean'],
            'is_public' => ['boolean', $this->encryptionPublicRule()],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_visible' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.unique' => 'A setting with this key already exists.',
            'is_public.prohibited_if' => 'Encrypted settings cannot be public for security reasons.',
            'type.Illuminate\Validation\Rules\Enum' => 'The selected type is invalid.',
        ];
    }

    /**
     * Get the validation rule to prevent encrypted settings from being public.
     */
    private function encryptionPublicRule(): string
    {
        return 'prohibited_if:is_encrypted,true';
    }
}
