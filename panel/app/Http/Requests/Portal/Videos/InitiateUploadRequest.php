<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal\Videos;

use App\Domain\Tenants\Contracts\TenantContextServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InitiateUploadRequest extends FormRequest
{
    /**
     * Common supported video mime types.
     */
    public const array ALLOWED_MIME_TYPES = [
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'video/avi',
        'video/msvideo',
        'video/x-matroska',
        'video/mkv',
        'video/webm',
        'video/x-m4v',
        'video/flv',
        'video/x-flv',
        'video/mp2t',
        'video/3gpp',
        'video/ogg',
    ];

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
        return [
            'filename' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1', 'max:107374182400'], // 100 GB max
            'content_type' => [
                'required',
                'string',
                'starts_with:video/',
                Rule::in(self::ALLOWED_MIME_TYPES),
            ],
        ];
    }
}
