<?php

declare(strict_types=1);

namespace App\Enums;

use DevWizardHQ\Enumify\Concerns\EnumHelpers;
use DevWizardHQ\Enumify\Contracts\HasLabels;

enum AssetType: string implements HasLabels
{
    use EnumHelpers;

    case ORIGINAL = 'original';
    case MASTER = 'master';
    case HLS_PLAYLIST = 'hls_playlist';
    case HLS_SEGMENT = 'hls_segment';
    case THUMBNAIL = 'thumbnail';

    public function label(): string
    {
        return match ($this) {
            self::ORIGINAL => __('Original'),
            self::MASTER => __('Master'),
            self::HLS_PLAYLIST => __('HLS Playlist'),
            self::HLS_SEGMENT => __('HLS Segment'),
            self::THUMBNAIL => __('Thumbnail'),
        };
    }

    public function isOriginal(): bool
    {
        return $this === self::ORIGINAL;
    }

    public function isMaster(): bool
    {
        return $this === self::MASTER;
    }

    public function isHls(): bool
    {
        return in_array($this, [self::HLS_PLAYLIST, self::HLS_SEGMENT], true);
    }

    public function isThumbnail(): bool
    {
        return $this === self::THUMBNAIL;
    }
}
