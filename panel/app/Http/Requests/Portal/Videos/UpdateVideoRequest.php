<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal\Videos;

use App\Domain\Tenants\Contracts\TenantContextServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Enums\VideoVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateVideoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Video|string|null $video */
        $video = $this->route('video');

        if (is_string($video)) {
            $video = Video::withoutTenantScope()->find($video);
        }

        if (! $video instanceof Video) {
            return false;
        }

        $currentTenant = app()->bound('current_tenant')
            ? app('current_tenant')
            : ($this->user() !== null ? app(TenantContextServiceInterface::class)->getCurrentTenant($this->user()) : null);

        if ($currentTenant instanceof Tenant && (string) $video->tenant_id !== (string) $currentTenant->id) {
            abort(404);
        }

        return $this->user()?->can('update', $video) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['sometimes', 'required', Rule::enum(VideoVisibility::class)],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
