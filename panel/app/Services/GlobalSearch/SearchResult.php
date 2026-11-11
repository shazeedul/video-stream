<?php

declare(strict_types=1);

namespace App\Services\GlobalSearch;

use App\Services\GlobalSearch\Contracts\SearchResultInterface;

final readonly class SearchResult implements SearchResultInterface
{
    public function __construct(
        private string $id,
        private string $title,
        private string $subtitle,
        private string $url,
        private string $category,
        private string $icon,
        private ?string $modelType = null
    ) {}

    public static function make(
        string $id,
        string $title,
        string $subtitle,
        string $url,
        string $category,
        string $icon,
        ?string $modelType = null
    ): self {
        return new self($id, $title, $subtitle, $url, $category, $icon, $modelType);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSubtitle(): string
    {
        return $this->subtitle;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getModelType(): ?string
    {
        return $this->modelType;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'url' => $this->url,
            'category' => $this->category,
            'icon' => $this->icon,
            'model_type' => $this->modelType,
        ];
    }
}
