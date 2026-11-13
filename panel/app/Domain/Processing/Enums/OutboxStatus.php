<?php

declare(strict_types=1);

namespace App\Domain\Processing\Enums;

use DevWizardHQ\Enumify\Concerns\EnumHelpers;
use DevWizardHQ\Enumify\Contracts\HasLabels;

enum OutboxStatus: string implements HasLabels
{
    use EnumHelpers;

    case PENDING = 'pending';
    case PUBLISHED = 'published';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::PUBLISHED => __('Published'),
            self::FAILED => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'amber',
            self::PUBLISHED => 'green',
            self::FAILED => 'red',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isPublished(): bool
    {
        return $this === self::PUBLISHED;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }
}
