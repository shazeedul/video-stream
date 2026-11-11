import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { SystemSetting } from '@/types/system-setting';

interface CheckboxFieldProps {
    setting: SystemSetting;
    onChange: (value: string) => void;
}

export function CheckboxField({ setting, onChange }: CheckboxFieldProps) {
    const isChecked = setting.value === '1' || setting.value === 'true';

    return (
        <div className="flex items-center space-x-2">
            <Checkbox id={setting.key} checked={isChecked} onCheckedChange={(checked) => onChange(checked ? '1' : '0')} />
            <Label htmlFor={setting.key} className="cursor-pointer font-normal">
                {setting.name}
            </Label>
        </div>
    );
}
