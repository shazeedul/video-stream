import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SystemSetting } from '@/types/system-setting';
import { useLocalizer } from '@devwizard/laravel-localizer-react';

interface TextFieldProps {
    setting: SystemSetting;
    onChange: (value: string) => void;
}

export function TextField({ setting, onChange }: TextFieldProps) {
    const { __ } = useLocalizer();
    const handleFocus = (e: React.FocusEvent<HTMLInputElement>) => {
        // Clear masked value on focus for encrypted fields
        if (setting.is_encrypted && setting.value === '••••••••') {
            onChange('');
            e.target.value = '';
        }
    };

    return (
        <div className="space-y-2">
            <Label htmlFor={setting.key}>{setting.name}</Label>
            <Input
                id={setting.key}
                type={setting.type}
                value={setting.value}
                onChange={(e) => onChange(e.target.value)}
                onFocus={handleFocus}
                placeholder={
                    setting.is_encrypted && setting.value === '••••••••'
                        ? __('Click to update encrypted value')
                        : __('Enter :name', { name: setting.name.toLowerCase() })
                }
            />
        </div>
    );
}
