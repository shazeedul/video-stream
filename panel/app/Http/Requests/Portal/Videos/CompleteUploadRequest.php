<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal\Videos;

use App\Domain\Tenants\Contracts\TenantContextServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CompleteUploadRequest extends FormRequest
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
        return [
            'parts' => ['required', 'array', 'min:1'],
            'parts.*' => ['required', 'array'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $parts = $this->input('parts');
            if (! is_array($parts)) {
                return;
            }

            foreach ($parts as $index => $part) {
                if (! is_array($part)) {
                    $validator->errors()->add("parts.{$index}", __('Each part must be an object.'));

                    continue;
                }

                $partNumber = $part['PartNumber'] ?? $part['part_number'] ?? null;
                $etag = $part['ETag'] ?? $part['etag'] ?? null;

                if ($partNumber === null || ! is_numeric($partNumber) || (int) $partNumber < 1) {
                    $validator->errors()->add("parts.{$index}.part_number", __('Each part must have a valid part number greater than 0.'));
                }

                if ($etag === null || ! is_string($etag) || mb_trim($etag) === '') {
                    $validator->errors()->add("parts.{$index}.etag", __('Each part must have a valid ETag.'));
                }
            }
        });
    }

    /**
     * Format validated parts for service consumption.
     *
     * @return array<int, array{PartNumber: int, ETag: string}>
     */
    public function formattedParts(): array
    {
        $parts = (array) $this->input('parts', []);
        $formatted = [];

        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }

            $partNumber = (int) ($part['PartNumber'] ?? $part['part_number'] ?? 0);
            $etag = (string) ($part['ETag'] ?? $part['etag'] ?? '');

            $formatted[] = [
                'PartNumber' => $partNumber,
                'ETag' => $etag,
            ];
        }

        return $formatted;
    }
}
