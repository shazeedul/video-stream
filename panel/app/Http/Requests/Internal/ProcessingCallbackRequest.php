<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ProcessingCallbackRequest extends FormRequest
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
            'job_id' => ['required', 'uuid'],
            'video_id' => ['required', 'uuid', 'exists:videos,id'],
            'status' => ['required', 'string', 'in:probing,transcoding,packaging,probed,ready,failed'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'error' => ['nullable', 'string', 'max:2000'],
            'thumbnail_key' => ['nullable', 'string', 'max:500'],
            'assets' => ['nullable', 'array'],
            'assets.*.type' => ['required', 'string'],
            'assets.*.disk' => ['required', 'string'],
            'assets.*.object_key' => ['required', 'string'],
            'assets.*.size_bytes' => ['required', 'integer', 'min:0'],
            'assets.*.mime_type' => ['required', 'string'],
            'assets.*.metadata' => ['nullable', 'array'],
        ];
    }
}
