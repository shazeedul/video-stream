<?php

declare(strict_types=1);

namespace App\Enums;

use DevWizardHQ\Enumify\Concerns\EnumHelpers;
use DevWizardHQ\Enumify\Contracts\HasLabels;

enum VideoVisibility: string implements HasLabels
{
    use EnumHelpers;

    case PRIVATE = 'private';
    case UNLISTED = 'unlisted';
    case PUBLIC = 'public';

    public function label(): string
    {
        return match ($this) {
            self::PRIVATE => __('Private'),
            self::UNLISTED => __('Unlisted'),
            self::PUBLIC => __('Public'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PRIVATE => 'gray',
            self::UNLISTED => 'amber',
            self::PUBLIC => 'green',
        };
    }

    public function isPrivate(): bool
    {
        return $this === self::PRIVATE;
    }

    public function isUnlisted(): bool
    {
        return $this === self::UNLISTED;
    }

    public function isPublic(): bool
    {
        return $this === self::PUBLIC;
    }
}
