<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal\Videos;

use App\Domain\Tenants\Contracts\TenantContextServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class GetPartUrlsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Video|null $video */
        $video = $this->route('video');

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
        /** @var Video|null $video */
        $video = $this->route('video');
        $session = $video?->uploadSessions()->latest()->first();
        $maxParts = $session?->total_parts;

        $partRule = ['required', 'integer', 'min:1'];
        if ($maxParts !== null && $maxParts > 0) {
            $partRule[] = "max:{$maxParts}";
        }

        return [
            'part_numbers' => ['required', 'array', 'min:1'],
            'part_numbers.*' => $partRule,
        ];
    }
}
