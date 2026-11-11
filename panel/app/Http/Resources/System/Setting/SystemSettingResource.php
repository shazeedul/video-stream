<?php

declare(strict_types=1);

namespace App\Http\Resources\System\Setting;

use App\Enums\SystemSettingType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

final class SystemSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group,
            'name' => $this->name,
            'key' => $this->key,
            'value' => $this->getMaskedValue(),
            'url' => $this->getFileUrl(),
            'type' => $this->type,
            'rules' => $this->rules ?? null, // Already cast to array by model
            'remarks' => $this->remarks,
            'is_encrypted' => $this->is_encrypted,
            'is_public' => $this->is_public,
            'sort_order' => $this->sort_order,
            'is_visible' => $this->is_visible,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get the masked value for encrypted settings.
     * Never expose the decrypted value to the frontend.
     */
    private function getMaskedValue(): string
    {
        if ($this->is_encrypted) {
            return '••••••••';
        }

        return (string) $this->value;
    }

    /**
     * Get the file URL for file/image type settings.
     * Returns the full URL using Storage::url() for proper CDN/S3 support.
     */
    private function getFileUrl(): ?string
    {
        // Only return URL for file and image types
        if (! in_array($this->type, [SystemSettingType::FILE, SystemSettingType::IMAGE])) {
            return null;
        }

        // Don't return URL for encrypted values or empty values
        if ($this->is_encrypted || ! $this->value) {
            return null;
        }

        return Storage::url($this->value);
    }
}
