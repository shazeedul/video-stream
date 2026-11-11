<?php

declare(strict_types=1);

namespace App\Services\GlobalSearch\Contracts;

use Illuminate\Database\Eloquent\Model;

interface SearchResultInterface
{
    /**
     * Get the unique identifier for this result.
     */
    public function getId(): string;

    /**
     * Get the main title/name for this result.
     */
    public function getTitle(): string;

    /**
     * Get the subtitle/description for this result.
     */
    public function getSubtitle(): string;

    /**
     * Get the URL for this result.
     */
    public function getUrl(): string;

    /**
     * Get the category this result belongs to.
     */
    public function getCategory(): string;

    /**
     * Get the icon for this result.
     */
    public function getIcon(): string;

    /**
     * Get the model type (if applicable).
     */
    public function getModelType(): ?string;

    /**
     * Convert to array format.
     */
    public function toArray(): array;
}
