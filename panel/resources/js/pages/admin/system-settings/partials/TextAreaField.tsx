import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { SystemSetting } from '@/types/system-setting';
import { useLocalizer } from '@devwizard/laravel-localizer-react';

interface TextAreaFieldProps {
    setting: SystemSetting;
    onChange: (value: string) => void;
}

export function TextAreaField({ setting, onChange }: TextAreaFieldProps) {
    const { __ } = useLocalizer();
    const handleFocus = (e: React.FocusEvent<HTMLTextAreaElement>) => {
        // Clear masked value on focus for encrypted fields
        if (setting.is_encrypted && setting.value === '••••••••') {
            onChange('');
            e.target.value = '';
        }
    };

    return (
        <div className="space-y-2">
            <Label htmlFor={setting.key}>{setting.name}</Label>
            <Textarea
                id={setting.key}
                value={setting.value}
                onChange={(e) => onChange(e.target.value)}
                onFocus={handleFocus}
                placeholder={
                    setting.is_encrypted && setting.value === '••••••••'
                        ? __('Click to update encrypted value')
                        : __('Enter :name', { name: setting.name.toLowerCase() })
                }
                rows={4}
            />
        </div>
    );
}
