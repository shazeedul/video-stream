<?php

declare(strict_types=1);

namespace App\Enums;

use DevWizardHQ\Enumify\Concerns\EnumHelpers;
use DevWizardHQ\Enumify\Contracts\HasLabels;

enum TenantRole: string implements HasLabels
{
    use EnumHelpers;

    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';
    case VIEWER = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => __('Owner'),
            self::ADMIN => __('Admin'),
            self::MEMBER => __('Member'),
            self::VIEWER => __('Viewer'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OWNER => 'purple',
            self::ADMIN => 'blue',
            self::MEMBER => 'green',
            self::VIEWER => 'gray',
        };
    }

    public function isOwner(): bool
    {
        return $this === self::OWNER;
    }

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function isMember(): bool
    {
        return $this === self::MEMBER;
    }

    public function isViewer(): bool
    {
        return $this === self::VIEWER;
    }

    public function canManageMembers(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN], true);
    }

    public function canManageVideos(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::MEMBER], true);
    }
}
