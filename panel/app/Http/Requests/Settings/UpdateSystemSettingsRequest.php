<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\SystemSettingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdateSystemSettingsRequest extends FormRequest
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
        return [
            'settings' => ['required', 'array'],
            'settings.*.id' => ['required', 'exists:system_settings,id'],
            'settings.*.group' => ['sometimes', 'required', 'string', 'max:255'],
            'settings.*.name' => ['sometimes', 'required', 'string', 'max:255'],
            'settings.*.value' => ['nullable', 'string'],
            'settings.*.type' => ['sometimes', 'required', new Enum(SystemSettingType::class)],
            'settings.*.rules' => ['nullable', 'json'],
            'settings.*.remarks' => ['nullable', 'string'],
            'settings.*.is_encrypted' => ['boolean'],
            'settings.*.is_public' => ['boolean', 'prohibited_if:settings.*.is_encrypted,true'],
            'settings.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'settings.*.is_visible' => ['boolean'],
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
            'settings.*.id.required' => 'Each setting must have an ID.',
            'settings.*.id.exists' => 'One or more settings do not exist.',
            'settings.*.is_public.prohibited_if' => 'Encrypted settings cannot be public for security reasons.',
            'settings.*.type.Illuminate\Validation\Rules\Enum' => 'The selected type is invalid.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Remove masked values from settings array
        $settings = $this->input('settings', []);

        foreach ($settings as $key => $setting) {
            if (isset($setting['value']) && $setting['value'] === '••••••••') {
                unset($settings[$key]['value']);
            }
        }

        $this->merge(['settings' => $settings]);
    }
}
