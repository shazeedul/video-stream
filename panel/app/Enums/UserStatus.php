<?php

declare(strict_types=1);

namespace App\Enums;

use DevWizardHQ\Enumify\Concerns\EnumHelpers;
use DevWizardHQ\Enumify\Contracts\HasLabels;

enum UserStatus: string implements HasLabels
{
    use EnumHelpers;

    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::SUSPENDED => __('Suspended'),
            self::INACTIVE => __('Inactive'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::SUSPENDED => 'red',
            self::INACTIVE => 'gray',
        };
    }

    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
