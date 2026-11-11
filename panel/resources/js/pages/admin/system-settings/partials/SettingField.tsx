import { SystemSettingType } from '@/enums';
import type { SystemSetting } from '@/types/system-setting';
import { CheckboxField } from './CheckboxField';
import { DateTimeField } from './DateTimeField';
import { CodeEditorField } from './fields/CodeEditorField';
import { RichTextBoxField } from './fields/RichTextBoxField';
import { FileUpload } from './FileUpload';
import { ImageUpload } from './ImageUpload';
import { SelectField } from './SelectField';
import { TextAreaField } from './TextAreaField';
import { TextField } from './TextField';

interface SettingFieldProps {
    setting: SystemSetting;
    onChange: (value: string | File | null) => void;
}

export function SettingField({ setting, onChange }: SettingFieldProps) {
    const { type } = setting;

    // Text-based inputs
    if (
        ([SystemSettingType.TEXT, SystemSettingType.EMAIL, SystemSettingType.NUMBER, SystemSettingType.URL, SystemSettingType.TEL] as string[]).includes(
            type,
        )
    ) {
        return <TextField setting={setting} onChange={onChange} />;
    }

    // Date/Time inputs
    if (
        ([SystemSettingType.DATE, SystemSettingType.TIME, SystemSettingType.DATETIME_LOCAL, SystemSettingType.MONTH, SystemSettingType.WEEK] as string[]).includes(
            type,
        )
    ) {
        return <DateTimeField setting={setting} onChange={onChange} />;
    }

    // Textarea
    if (type === SystemSettingType.TEXT_AREA) {
        return <TextAreaField setting={setting} onChange={onChange} />;
    }

    // Rich Text Editor
    if (type === SystemSettingType.RICH_TEXT_BOX) {
        const rules =
            typeof setting.rules === 'object' && setting.rules !== null ? (setting.rules as Record<string, string | number | boolean>) : undefined;
        return <RichTextBoxField value={setting.value} onChange={onChange} rules={rules} />;
    }

    // Code Editor
    if (type === SystemSettingType.CODE_EDITOR) {
        const rules =
            typeof setting.rules === 'object' && setting.rules !== null ? (setting.rules as Record<string, string | number | boolean>) : undefined;
        return <CodeEditorField value={setting.value} onChange={onChange} rules={rules} />;
    }

    // Checkbox
    if (type === SystemSettingType.CHECKBOX) {
        return <CheckboxField setting={setting} onChange={onChange} />;
    }

    // Select dropdown
    if (type === SystemSettingType.SELECT_DROPDOWN) {
        return <SelectField setting={setting} onChange={onChange} />;
    }

    // File upload
    if (type === SystemSettingType.FILE) {
        return <FileUpload setting={setting} onChange={onChange} />;
    }

    // Image upload
    if (type === SystemSettingType.IMAGE) {
        return <ImageUpload setting={setting} onChange={onChange} />;
    }

    // Fallback to text input for unsupported types
    return <TextField setting={setting} onChange={onChange} />;
}
