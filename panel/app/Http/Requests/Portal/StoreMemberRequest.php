<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Domain\Tenants\Contracts\TenantContextServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreMemberRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', 'exists:users,email'],
            'role' => ['required', Rule::enum(TenantRole::class)],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $currentTenant = $this->currentTenant();
            if ($currentTenant === null) {
                return;
            }

            $email = $this->input('email');
            if (! is_string($email) || mb_trim($email) === '') {
                return;
            }

            $user = User::query()->where('email', mb_trim($email))->first();
            if ($user !== null && $currentTenant->memberships()->where('user_id', $user->id)->exists()) {
                $validator->errors()->add(
                    'email',
                    __('This user is already a member of this organization.')
                );
            }
        });
    }
}
