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
            'status' => ['required', 'string', 'in:probing,probed,failed'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'error' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
