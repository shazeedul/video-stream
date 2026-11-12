<?php

declare(strict_types=1);

namespace App\Enums;

use DevWizardHQ\Enumify\Concerns\EnumHelpers;
use DevWizardHQ\Enumify\Contracts\HasLabels;

enum VideoStatus: string implements HasLabels
{
    use EnumHelpers;

    case DRAFT = 'draft';
    case UPLOADING = 'uploading';
    case UPLOADED = 'uploaded';
    case QUEUED = 'queued';
    case PROBING = 'probing';
    case TRANSCODING = 'transcoding';
    case PACKAGING = 'packaging';
    case READY = 'ready';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::UPLOADING => __('Uploading'),
            self::UPLOADED => __('Uploaded'),
            self::QUEUED => __('Queued'),
            self::PROBING => __('Probing'),
            self::TRANSCODING => __('Transcoding'),
            self::PACKAGING => __('Packaging'),
            self::READY => __('Ready'),
            self::FAILED => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::UPLOADING => 'blue',
            self::UPLOADED => 'indigo',
            self::QUEUED => 'amber',
            self::PROBING => 'purple',
            self::TRANSCODING => 'cyan',
            self::PACKAGING => 'orange',
            self::READY => 'green',
            self::FAILED => 'red',
        };
    }

    public function isReady(): bool
    {
        return $this === self::READY;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    public function isProcessing(): bool
    {
        return in_array($this, [
            self::QUEUED,
            self::PROBING,
            self::TRANSCODING,
            self::PACKAGING,
        ], true);
    }

    public function canBeEdited(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::UPLOADED,
            self::READY,
            self::FAILED,
        ], true);
    }
}
