<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

final class UnlinkSocialAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = Auth::user();

        // If user doesn't have a password and this is their only social account,
        // require password fields
        if ($user && $user->isSocialOnly() && $user->socialAccounts()->count() === 1) {
            return [
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
                'password_confirmation' => ['required', 'string'],
            ];
        }

        return [];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'password.required' => 'A password is required to unlink your last social account.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }
}
