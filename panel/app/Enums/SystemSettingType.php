<?php

declare(strict_types=1);

namespace App\Enums;

use DevWizardHQ\Enumify\Concerns\EnumHelpers;
use DevWizardHQ\Enumify\Contracts\HasLabels;

enum SystemSettingType: string implements HasLabels
{
    use EnumHelpers;

    case TEXT = 'text';
    case EMAIL = 'email';
    case NUMBER = 'number';
    case DATE = 'date';
    case MONTH = 'month';
    case WEEK = 'week';
    case DATETIME_LOCAL = 'datetime-local';
    case TIME = 'time';
    case URL = 'url';
    case TEL = 'tel';
    case TEXT_AREA = 'text_area';
    case RICH_TEXT_BOX = 'rich_text_box';
    case CODE_EDITOR = 'code_editor';
    case CHECKBOX = 'checkbox';
    case RADIO_BTN = 'radio_btn';
    case SELECT_DROPDOWN = 'select_dropdown';
    case FILE = 'file';
    case IMAGE = 'image';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => __('Text'),
            self::EMAIL => __('Email'),
            self::NUMBER => __('Number'),
            self::DATE => __('Date'),
            self::MONTH => __('Month'),
            self::WEEK => __('Week'),
            self::DATETIME_LOCAL => __('Date Time'),
            self::TIME => __('Time'),
            self::URL => __('URL'),
            self::TEL => __('Telephone'),
            self::TEXT_AREA => __('Text Area'),
            self::RICH_TEXT_BOX => __('Rich Text Editor'),
            self::CODE_EDITOR => __('Code Editor'),
            self::CHECKBOX => __('Checkbox'),
            self::RADIO_BTN => __('Radio Button'),
            self::SELECT_DROPDOWN => __('Dropdown Select'),
            self::FILE => __('File Upload'),
            self::IMAGE => __('Image Upload'),
        };
    }
}
