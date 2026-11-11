<?php

declare(strict_types=1);

namespace App\Enums;

use DevWizardHQ\Enumify\Concerns\EnumHelpers;
use DevWizardHQ\Enumify\Contracts\HasLabels;

enum TenantUserStatus: string implements HasLabels
{
    use EnumHelpers;

    case ACTIVE = 'active';
    case INVITED = 'invited';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::INVITED => __('Invited'),
            self::SUSPENDED => __('Suspended'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::INVITED => 'blue',
            self::SUSPENDED => 'red',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isInvited(): bool
    {
        return $this === self::INVITED;
    }

    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }
}
