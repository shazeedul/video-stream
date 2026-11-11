import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { SystemSetting } from '@/types/system-setting';
import { useLocalizer } from '@devwizard/laravel-localizer-react';

interface SelectFieldProps {
    setting: SystemSetting;
    onChange: (value: string) => void;
}

export function SelectField({ setting, onChange }: SelectFieldProps) {
    const { __ } = useLocalizer();
    const rules = typeof setting.rules === 'object' && setting.rules !== null ? setting.rules : {};
    const options = rules.options || {};

    // Convert options to array format
    // Format: {"label": "value"} becomes [{value: "value", label: "label"}]
    // Example: {"10 items": "10"} becomes [{value: "10", label: "10 items"}]
    const optionsArray = Array.isArray(options)
        ? options
        : Object.entries(options).map(([label, value]) => ({
              value: String(value),
              label: String(label),
          }));

    // Ensure value is a string for proper comparison
    const currentValue = setting.value ? String(setting.value) : undefined;

    return (
        <div className="space-y-2">
            <Label htmlFor={setting.key}>{setting.name}</Label>
            <Select value={currentValue} onValueChange={onChange}>
                <SelectTrigger id={setting.key}>
                    <SelectValue placeholder={__('Select :name', { name: setting.name.toLowerCase() })} />
                </SelectTrigger>
                <SelectContent>
                    {optionsArray.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
