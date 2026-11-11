<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\SystemSettingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateSystemSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('system-settings.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the setting ID from the route parameter
        $settingId = $this->route('systemSetting');

        return [
            'group' => ['sometimes', 'required', 'string', 'max:255'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'key' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('system_settings', 'key')->ignore($settingId)],
            'value' => ['nullable', 'string'],
            'file' => ['nullable', 'file'],
            'type' => ['sometimes', 'required', new Enum(SystemSettingType::class)],
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
