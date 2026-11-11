<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SystemSettingType;
use App\Facades\SystemSetting as SystemSettingFacade;
use Exception;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[\Illuminate\Database\Eloquent\Attributes\Fillable([
    'group',
    'name',
    'key',
    'value',
    'type',
    'rules',
    'remarks',
    'is_encrypted',
    'is_public',
    'sort_order',
    'is_visible',
])]
final class SystemSetting extends Model
{
    use HasFactory;

    /**
     * Get all distinct groups.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function groups(): \Illuminate\Support\Collection
    {
        return self::query()
            ->select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');
    }

    /**
     * Get the next available sort order for this setting's group.
     */
    public function getNextOrder(): int
    {
        $group = $this->group ?? 'general';

        return self::query()
            ->where('group', $group)
            ->max('sort_order') + 1;
    }

    /**
     * Move the setting down within its group.
     */
    public function moveDown(): bool
    {
        return $this->swapOrder(
            self::query()
                ->where('group', $this->group)
                ->where('sort_order', '>', $this->sort_order)
                ->orderBy('sort_order')
                ->first()
        );
    }

    /**
     * Move the setting up within its group.
     */
    public function moveUp(): bool
    {
        return $this->swapOrder(
            self::query()
                ->where('group', $this->group)
                ->where('sort_order', '<', $this->sort_order)
                ->orderBy('sort_order', 'desc')
                ->first()
        );
    }

    /**
     * Check if this setting is encrypted.
     */
    public function isEncrypted(): bool
    {
        return $this->is_encrypted;
    }

    /**
     * Check if this setting is public.
     */
    public function isPublic(): bool
    {
        return $this->is_public;
    }

    /**
     * Check if this setting can be exposed publicly.
     * Only non-encrypted settings can be public.
     */
    public function canBePublic(): bool
    {
        return ! $this->is_encrypted;
    }

    /**
     * Boot the model and set up event listeners.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Auto-set sort order for new settings
        self::creating(function (SystemSetting $model): void {
            if (is_null($model->sort_order)) {
                $model->sort_order = $model->getNextOrder();
            }
        });

        // Flush cache on model changes
        self::created(SystemSettingFacade::flush(...));
        self::updated(SystemSettingFacade::flush(...));
        self::deleted(SystemSettingFacade::flush(...));
        self::saved(SystemSettingFacade::flush(...));
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'is_public' => 'boolean',
            'is_encrypted' => 'boolean',
            'rules' => 'array',
            'type' => SystemSettingType::class,
        ];
    }

    /**
     * Get and set the value attribute with conditional encryption.
     *
     * If is_encrypted is true, automatically encrypt/decrypt the value.
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($this->is_encrypted && $value !== null && $value !== '') {
                    try {
                        return decrypt($value);
                    } catch (Exception) {
                        // If decryption fails, return null or original value
                        return null;
                    }
                }

                return $value;
            },
            set: function (?string $value): ?string {
                if ($this->is_encrypted && $value !== null && $value !== '') {
                    return encrypt($value);
                }

                return $value;
            }
        );
    }

    /**
     * Scope to filter by specific key.
     */
    #[Scope]
    protected function key(Builder $query, string $key): void
    {
        $query->where('key', $key);
    }

    /**
     * Scope to filter by visibility.
     */
    #[Scope]
    protected function visible(Builder $query, bool $isVisible = true): void
    {
        $query->where('is_visible', $isVisible);
    }

    /**
     * Scope to filter by public status.
     */
    #[Scope]
    protected function public(Builder $query, bool $isPublic = true): void
    {
        $query->where('is_public', $isPublic);
    }

    /**
     * Scope to filter by encrypted status.
     */
    #[Scope]
    protected function encrypted(Builder $query, bool $isEncrypted = true): void
    {
        $query->where('is_encrypted', $isEncrypted);
    }

    /**
     * Scope to filter by group.
     */
    #[Scope]
    protected function whereGroup(Builder $query, string $group): void
    {
        $query->where('group', $group);
    }

    /**
     * Scope to sort by sort_order.
     */
    #[Scope]
    protected function sortOrder(Builder $query, string $direction = 'asc'): void
    {
        $query->orderBy('sort_order', $direction);
    }

    /**
     * Get the raw key (without group prefix).
     */
    protected function rawKey(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $key = $this->attributes['key'] ?? '';
                $parts = explode('.', $key, 2);

                return $parts[1] ?? null;
            }
        );
    }

    /**
     * Swap sort order with another setting.
     */
    private function swapOrder(?self $otherSetting): bool
    {
        if ($otherSetting instanceof self) {
            $currentOrder = $this->sort_order;
            $this->update(['sort_order' => $otherSetting->sort_order]);
            $otherSetting->update(['sort_order' => $currentOrder]);

            return true;
        }

        return false;
    }
}
