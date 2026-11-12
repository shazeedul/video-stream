<?php

declare(strict_types=1);

namespace App\Enums;

use DevWizardHQ\Enumify\Concerns\EnumHelpers;
use DevWizardHQ\Enumify\Contracts\HasLabels;

enum UploadSessionStatus: string implements HasLabels
{
    use EnumHelpers;

    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case ABORTED = 'aborted';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::COMPLETED => __('Completed'),
            self::ABORTED => __('Aborted'),
            self::EXPIRED => __('Expired'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'amber',
            self::COMPLETED => 'green',
            self::ABORTED => 'red',
            self::EXPIRED => 'gray',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isAborted(): bool
    {
        return $this === self::ABORTED;
    }

    public function isExpired(): bool
    {
        return $this === self::EXPIRED;
    }
}
